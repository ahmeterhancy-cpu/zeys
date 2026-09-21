<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\OrderShipping;
use App\Services\PaymentRefunds;
use App\Services\Returns;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class PayTrIadeTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-key';

    private const SALT = 'test-salt';

    private User $yonetici;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::preventStrayRequests();

        config([
            'paytr.merchant_id' => '123456',
            'paytr.merchant_key' => self::KEY,
            'paytr.merchant_salt' => self::SALT,
        ]);

        $this->yonetici = User::factory()->create(['role' => 'admin']);

        $urun = Product::create(['name' => 'Saten Midi Elbise', 'is_active' => true]);
        app(VariantMatrix::class)->generate($urun, ['Beden' => ['kind' => 'text', 'values' => ['M']]], 2890.00);
        $urun->fresh()->variants->each->update(['stock' => 5]);

        app(Cart::class)->add($urun->fresh()->variants->first(), 2);

        $this->order = app(Checkout::class)->place(
            ['name' => 'Ayşe', 'email' => 'a@example.test', 'phone' => '555'],
            ['name' => 'Ayşe', 'phone' => '555', 'line1' => 'X', 'district' => 'Y', 'city' => 'Edirne'],
        );

        $this->order->update(['payment_ref' => 'ZEY2609210001X'.$this->order->id, 'payment_provider' => 'paytr']);
        app(OrderPayments::class)->markPaid($this->order->fresh());
    }

    private function onaylanmisIade(): ReturnRequest
    {
        $shipping = app(OrderShipping::class);
        $shipping->markShipped($this->order->fresh(), 'Yurtiçi', '1');
        $shipping->markDelivered($this->order->fresh());

        $order = $this->order->fresh('items');
        $talep = app(Returns::class)->open($order, 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        app(Returns::class)->markReceived($talep);
        app(Returns::class)->approve($talep->fresh());

        return $talep->fresh();
    }

    public function test_iade_istegi_tl_dizgisi_ve_dogru_imzayla_gider(): void
    {
        Http::fake(['paytr.com/odeme/iade' => Http::response(['status' => 'success'])]);

        $talep = $this->onaylanmisIade();

        app(PaymentRefunds::class)->refundReturn($talep);

        Http::assertSent(function (Request $istek) {
            $oid = $this->order->fresh()->payment_ref;
            $beklenen = base64_encode(hash_hmac('sha256', '123456'.$oid.'2890.00'.self::SALT, self::KEY, true));

            return $istek->url() === 'https://www.paytr.com/odeme/iade'
                // KURUS DEGIL: "289000" gitseydi 100 kat iade olurdu
                && $istek['return_amount'] === '2890.00'
                && $istek['merchant_oid'] === $oid
                && $istek['paytr_token'] === $beklenen;
        });
    }

    public function test_basarili_iade_talebi_tamamlar_ve_kilitler(): void
    {
        Http::fake(['paytr.com/odeme/iade' => Http::response(['status' => 'success'])]);

        $talep = $this->onaylanmisIade();
        app(PaymentRefunds::class)->refundReturn($talep);

        $talep->refresh();
        $this->assertSame('completed', $talep->status);
        $this->assertNotNull($talep->payment_refunded_at);

        $kayit = $this->order->fresh()->payment_meta['iade_kayitlari'][0];
        $this->assertSame('2890.00', $kayit['tutar']);
    }

    public function test_ayni_talep_iki_kez_iade_edilemez(): void
    {
        Http::fake(['paytr.com/odeme/iade' => Http::response(['status' => 'success'])]);

        $talep = $this->onaylanmisIade();
        app(PaymentRefunds::class)->refundReturn($talep);

        try {
            app(PaymentRefunds::class)->refundReturn($talep->fresh());
            $this->fail('Ikinci iade reddedilmeliydi');
        } catch (RuntimeException) {
            // beklenen
        }

        Http::assertSentCount(1);
    }

    public function test_paytr_reddederse_talep_degismez(): void
    {
        Http::fake(['paytr.com/odeme/iade' => Http::response(['status' => 'failed', 'err_msg' => 'Bakiye yetersiz'])]);

        $talep = $this->onaylanmisIade();

        try {
            app(PaymentRefunds::class)->refundReturn($talep);
            $this->fail('Hata firlatilmaliydi');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Bakiye yetersiz', $e->getMessage());
        }

        $talep->refresh();
        $this->assertSame('approved', $talep->status);
        $this->assertNull($talep->payment_refunded_at);
    }

    public function test_panel_eylemi_iade_sonrasi_gizlenir(): void
    {
        Http::fake(['paytr.com/odeme/iade' => Http::response(['status' => 'success'])]);

        $talep = $this->onaylanmisIade();

        Livewire::actingAs($this->yonetici)
            ->test(ListReturnRequests::class)
            ->assertTableActionVisible('paytrIade', $talep)
            ->callTableAction('paytrIade', $talep);

        // Talep tamamlandi — acik talepler suzgecinden cikti, eylem de gorunmemeli
        $this->assertNotNull($talep->fresh()->payment_refunded_at);
        $this->assertSame('completed', $talep->fresh()->status);
    }

    public function test_paytr_tanimli_degilse_eylem_gorunmez(): void
    {
        config(['paytr.merchant_id' => '', 'paytr.merchant_key' => '', 'paytr.merchant_salt' => '']);

        $talep = $this->onaylanmisIade();

        Livewire::actingAs($this->yonetici)
            ->test(ListReturnRequests::class)
            ->assertTableActionHidden('paytrIade', $talep);
    }

    public function test_odenmis_siparis_iptalinde_para_iade_edilir(): void
    {
        Http::fake(['paytr.com/odeme/iade' => Http::response(['status' => 'success'])]);

        Livewire::actingAs($this->yonetici)
            ->test(ListOrders::class)
            ->callTableAction('iptal', $this->order->fresh(), ['para_iade' => true]);

        $order = $this->order->fresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('5780.00', $order->refunded_total);

        Http::assertSent(fn (Request $i) => $i['return_amount'] === '5780.00');
    }

    public function test_iade_basarisizsa_siparis_iptal_edilmez(): void
    {
        Http::fake(['paytr.com/odeme/iade' => Http::response(['status' => 'failed', 'err_msg' => 'Hata'])]);

        Livewire::actingAs($this->yonetici)
            ->test(ListOrders::class)
            ->callTableAction('iptal', $this->order->fresh(), ['para_iade' => true]);

        // "Iptal edildi ama para magazada" yarim durumu olmamali
        $order = $this->order->fresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('committed', $order->stock_state);
    }
}
