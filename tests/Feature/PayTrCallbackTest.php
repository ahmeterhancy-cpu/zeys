<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\Payments\PayTrGateway;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PayTrCallbackTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-merchant-key';

    private const SALT = 'test-merchant-salt';

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paytr.merchant_id' => '123456',
            'paytr.merchant_key' => self::KEY,
            'paytr.merchant_salt' => self::SALT,
        ]);

        $product = Product::create(['name' => 'Triko Kazak', 'base_sku' => 'ZEYS-003']);

        app(VariantMatrix::class)->generate($product, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
        ], 1000.00);

        $this->variant = $product->variants()->first();
        $this->variant->update(['stock' => 10]);
    }

    private function siparis(): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->variant, 2);

        $order = app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'Bağdat Cad. 1', 'district' => 'Kadıköy', 'city' => 'İstanbul'],
        );

        $order->update(['payment_ref' => 'ZEY2609200001X'.$order->id]);

        return $order->fresh();
    }

    /** @param  array<string,mixed>  $extra */
    private function bildirim(Order $order, string $status, ?int $amount = null, array $extra = []): array
    {
        $oid = $order->payment_ref;
        $total = (string) ($amount ?? (int) round((float) $order->grand_total * 100));

        return array_merge([
            'merchant_oid' => $oid,
            'status' => $status,
            'total_amount' => $total,
            'hash' => base64_encode(hash_hmac('sha256', $oid.self::SALT.$status.$total, self::KEY, true)),
        ], $extra);
    }

    public function test_gecerli_bildirim_siparisi_odendi_yapar_ve_stok_duser(): void
    {
        $order = $this->siparis();

        $response = $this->post('/paytr/callback', $this->bildirim($order, 'success'));

        $response->assertOk();
        // PayTR govdesi tam olarak "OK" olmayan her yaniti basarisiz sayar
        $this->assertSame('OK', $response->getContent());

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('committed', $order->stock_state);

        $this->variant->refresh();
        $this->assertSame(8, $this->variant->stock);
        $this->assertSame(0, $this->variant->reserved);
    }

    public function test_bozuk_imza_reddedilir_ve_siparis_degismez(): void
    {
        $order = $this->siparis();

        $payload = $this->bildirim($order, 'success');
        $payload['hash'] = 'sahte-imza';

        $this->post('/paytr/callback', $payload)->assertStatus(400);

        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('reserved', $order->stock_state);
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_ayni_bildirim_tekrar_gelirse_stok_iki_kez_dusmez(): void
    {
        $order = $this->siparis();
        $payload = $this->bildirim($order, 'success');

        $this->post('/paytr/callback', $payload)->assertOk();
        $this->post('/paytr/callback', $payload)->assertOk();
        $this->post('/paytr/callback', $payload)->assertOk();

        $this->assertSame(8, $this->variant->fresh()->stock, 'Tekrarlanan bildirim stogu bir kez dusurmeli');
    }

    public function test_basarisiz_odeme_rezervi_serbest_birakir(): void
    {
        $order = $this->siparis();

        $this->assertSame(2, $this->variant->fresh()->reserved);

        $response = $this->post('/paytr/callback', $this->bildirim($order, 'failed', null, [
            'failed_reason_msg' => 'Yetersiz bakiye',
        ]));

        $this->assertSame('OK', $response->getContent());

        $order->refresh();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('none', $order->stock_state);

        $this->variant->refresh();
        $this->assertSame(10, $this->variant->stock);
        $this->assertSame(0, $this->variant->reserved, 'Rezerv geri verilmeli');
    }

    public function test_eksik_tutar_siparisi_odendi_yapmaz(): void
    {
        $order = $this->siparis();
        $beklenen = (int) round((float) $order->grand_total * 100);

        // Beklenenin yarisi kadar para geldi
        $response = $this->post('/paytr/callback', $this->bildirim($order, 'success', (int) ($beklenen / 2)));

        // PayTR tekrar gondermesin diye yine OK
        $this->assertSame('OK', $response->getContent());

        $order->refresh();
        $this->assertNotSame('paid', $order->payment_status, 'Eksik tahsilatla siparis odendi sayilmamali');
        $this->assertStringContainsString('TUTAR UYUŞMAZLIĞI', $order->admin_note);
        $this->assertSame(10, $this->variant->fresh()->stock, 'Stok dusmemeli');
    }

    public function test_fazla_tutar_kabul_edilir_taksit_komisyonu(): void
    {
        $order = $this->siparis();
        $beklenen = (int) round((float) $order->grand_total * 100);

        // Taksit komisyonu musteriye yansitilinca total_amount buyur
        $this->post('/paytr/callback', $this->bildirim($order, 'success', $beklenen + 15000, [
            'installment_count' => '3',
        ]))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_bilinmeyen_siparis_icin_ok_donulur(): void
    {
        $oid = 'BILINMEYEN123';
        $payload = [
            'merchant_oid' => $oid,
            'status' => 'success',
            'total_amount' => '1000',
            'hash' => base64_encode(hash_hmac('sha256', $oid.self::SALT.'success1000', self::KEY, true)),
        ];

        $response = $this->post('/paytr/callback', $payload);

        // Aksi halde PayTR sonsuza kadar tekrar gonderir
        $this->assertSame('OK', $response->getContent());
    }

    public function test_callback_yolunda_oturum_ara_katmani_yok(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'paytr/callback');

        $middleware = $route->gatherMiddleware();

        // Oturum acilirsa musterinin cerezi bos bir oturumla ezilir
        $this->assertNotContains(StartSession::class, $middleware);
        $this->assertNotContains(ValidateCsrfToken::class, $middleware);
    }

    public function test_donus_adresi_imzasiz_acilmaz(): void
    {
        $order = $this->siparis();

        // Imzasiz: siradaki siparis numarasini deneyen biri gormemeli
        $this->get('/odeme/donus/'.$order->number)->assertStatus(403);

        $imzali = URL::signedRoute('payment.return', ['order' => $order->number]);
        $this->get($imzali)->assertOk();
    }

    public function test_donus_sayfasi_odeme_onaylanmadan_basarili_demez(): void
    {
        $order = $this->siparis();

        $imzali = URL::signedRoute('payment.return', ['order' => $order->number]);

        $this->get($imzali)
            ->assertOk()
            ->assertSee('isleniyor', false)
            ->assertDontSee('Siparisiniz alindi', false);
    }

    public function test_token_imzasi_paytr_formuluyle_uyusur(): void
    {
        $order = $this->siparis();
        $gateway = app(PayTrGateway::class);

        $this->assertTrue($gateway->isConfigured());
        $this->assertSame(200000, $gateway->kurus(2000.00));

        // Callback dogrulamasi ayni formulu ters yonde kullanir
        $this->assertTrue($gateway->verifyCallback($this->bildirim($order, 'success')));

        // DIKKAT: array + array soldaki anahtari KORUR, ezmez. array_merge sart.
        $bozuk = array_merge($this->bildirim($order, 'success'), ['hash' => 'x']);
        $this->assertFalse($gateway->verifyCallback($bozuk));

        // Tutar degisince imza da gecersizlesmeli
        $oynanmis = array_merge($this->bildirim($order, 'success'), ['total_amount' => '1']);
        $this->assertFalse($gateway->verifyCallback($oynanmis));
    }
}
