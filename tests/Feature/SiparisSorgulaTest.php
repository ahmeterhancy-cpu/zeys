<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\OrderShipping;
use App\Services\Returns;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SiparisSorgulaTest extends TestCase
{
    use RefreshDatabase;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->urun = Product::create([
            'name' => 'Saten Midi Elbise',
            'base_sku' => 'ZEYS-001',
            'is_active' => true,
        ]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M', 'L']],
        ], 2890.00);

        $this->urun->fresh()->variants->each->update(['stock' => 5]);
    }

    private function varyant(string $etiket): ProductVariant
    {
        return $this->urun->fresh()->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $etiket);
    }

    private function siparis(int $adet = 2): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), $adet);

        $order = app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'Bağdat Cad. 1', 'district' => 'Kadıköy', 'city' => 'İstanbul'],
        );

        app(OrderPayments::class)->markPaid($order);

        return $order->fresh('items');
    }

    private function teslimEt(Order $order): Order
    {
        $shipping = app(OrderShipping::class);
        $shipping->markShipped($order->fresh(), 'Yurtiçi', '123');
        $shipping->markDelivered($order->fresh());

        return $order->fresh('items');
    }

    // --- Sorgulama ---

    public function test_sorgu_formu_acilir(): void
    {
        $this->get('/siparis-sorgula')->assertOk()->assertSee('Sipariş Sorgula');
    }

    public function test_dogru_bilgilerle_siparise_yonlendirir(): void
    {
        $order = $this->siparis();

        $this->post('/siparis-sorgula', [
            'siparis_no' => $order->number,
            'eposta' => 'ayse@example.test',
        ])->assertRedirectContains('/siparis/'.$order->number);
    }

    public function test_eposta_buyuk_kucuk_harf_ayrimi_yapmaz(): void
    {
        $order = $this->siparis();

        $this->post('/siparis-sorgula', [
            'siparis_no' => $order->number,
            'eposta' => 'AYSE@Example.Test',
        ])->assertRedirectContains('/siparis/');
    }

    public function test_yanlis_eposta_ile_siparis_acilmaz(): void
    {
        $order = $this->siparis();

        $this->post('/siparis-sorgula', [
            'siparis_no' => $order->number,
            'eposta' => 'baskasi@example.test',
        ])->assertSessionHasErrors('siparis_no');
    }

    public function test_hata_mesaji_siparisin_varligini_ele_vermez(): void
    {
        $order = $this->siparis();

        /*
         * Var olan siparis + yanlis eposta ile, hic olmayan siparis
         * AYNI mesaji vermeli; yoksa numara deneyerek hangi siparisin
         * var oldugu ogrenilebilir.
         */
        $varOlan = $this->post('/siparis-sorgula', [
            'siparis_no' => $order->number,
            'eposta' => 'baskasi@example.test',
        ])->assertSessionHasErrors('siparis_no');

        $olmayan = $this->post('/siparis-sorgula', [
            'siparis_no' => 'ZEY-991231-9999',
            'eposta' => 'baskasi@example.test',
        ])->assertSessionHasErrors('siparis_no');

        $this->assertSame(
            session()->get('errors')?->first('siparis_no'),
            $olmayan->getSession()->get('errors')->first('siparis_no')
        );
    }

    public function test_imzasiz_siparis_sayfasi_acilmaz(): void
    {
        $order = $this->siparis();

        // Siradaki numarayi deneyen biri baskasinin siparisini gormemeli
        $this->get('/siparis/'.$order->number)->assertStatus(403);
    }

    public function test_imzali_adres_siparisi_gosterir(): void
    {
        $order = $this->siparis();

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Saten Midi Elbise')
            ->assertSee('5.780,00 TL');
    }

    public function test_kargo_bilgisi_siparis_sayfasinda_gorunur(): void
    {
        $order = $this->siparis();
        app(OrderShipping::class)->markShipped($order->fresh(), 'Yurtiçi Kargo', '9876543210');

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertOk()
            ->assertSee('9876543210')
            ->assertSee('Yurtiçi Kargo');
    }

    // --- Müşteri iade talebi ---

    public function test_musteri_iade_talebi_acabilir(): void
    {
        $order = $this->teslimEt($this->siparis(2));
        $kalem = $order->items->first();

        $this->post(URL::signedRoute('order.return', ['order' => $order->number]), [
            'tur' => 'return',
            'gerekce' => 'beden',
            'not' => 'Bir beden büyük geldi.',
            'kalemler' => [
                $kalem->id => ['sec' => '1', 'adet' => 1],
            ],
        ])->assertRedirect();

        $talep = ReturnRequest::firstOrFail();
        $this->assertSame('return', $talep->type);
        $this->assertSame('opened', $talep->status);
        $this->assertSame(1, $talep->items->first()->quantity);
        $this->assertSame('Bir beden büyük geldi.', $talep->customer_note);
    }

    /**
     * GERİLEME: sayfadaki form imzasız adrese post ediyordu → 403. Buradaki
     * diğer testler adresi elle imzaladığı için hata görünmüyordu; bu test
     * adresi SAYFADAKİ formdan okur.
     */
    public function test_sayfadaki_iade_formu_gercekten_gonderilebilir(): void
    {
        $order = $this->teslimEt($this->siparis(2));
        $kalem = $order->items->first();

        $html = $this->get(URL::signedRoute('order.show', ['order' => $order->number]))->getContent();
        $this->assertSame(1, preg_match('/<form method="POST" action="([^"]+)" class="iade-formu"/', $html, $m));

        $this->post(html_entity_decode($m[1]), [
            'tur' => 'return',
            'gerekce' => 'beden',
            'kalemler' => [$kalem->id => ['sec' => '1', 'adet' => 1]],
        ])->assertRedirect();

        $this->assertSame(1, ReturnRequest::count());
    }

    public function test_musteri_degisim_talebinde_hedef_beden_kaydedilir(): void
    {
        $order = $this->teslimEt($this->siparis(1));
        $kalem = $order->items->first();
        $l = $this->varyant('L');

        $this->post(URL::signedRoute('order.return', ['order' => $order->number]), [
            'tur' => 'exchange',
            'gerekce' => 'beden',
            'kalemler' => [
                $kalem->id => ['sec' => '1', 'adet' => 1, 'degisim_varyant' => $l->id],
            ],
        ])->assertRedirect();

        $talep = ReturnRequest::firstOrFail();
        $this->assertSame('exchange', $talep->type);
        $this->assertSame($l->id, $talep->items->first()->exchange_variant_id);
    }

    public function test_hicbir_urun_secilmezse_talep_acilmaz(): void
    {
        $order = $this->teslimEt($this->siparis());
        $kalem = $order->items->first();

        $this->post(URL::signedRoute('order.return', ['order' => $order->number]), [
            'tur' => 'return',
            'gerekce' => 'beden',
            'kalemler' => [$kalem->id => ['sec' => null, 'adet' => 1]],
        ])->assertSessionHas('hata');

        $this->assertSame(0, ReturnRequest::count());
    }

    public function test_cayma_suresi_dolmussa_form_gosterilmez(): void
    {
        $order = $this->teslimEt($this->siparis());
        $order->forceFill(['delivered_at' => now()->subDays(20)])->save();

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertOk()
            ->assertSee('Cayma hakkı süresi dolmuş', false)
            ->assertDontSee('Talep oluştur');
    }

    public function test_odenmemis_sipariste_talep_acilamaz(): void
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), 1);

        $order = app(Checkout::class)->place(
            ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
            ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
        );

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertOk()
            ->assertSee('Ödemesi tamamlanmamış', false);
    }

    public function test_ayni_urun_icin_ikinci_talep_acilamaz(): void
    {
        $order = $this->teslimEt($this->siparis(2));
        $kalem = $order->items->first();

        app(Returns::class)->open($order, 'return', 'beden', [
            $kalem->id => ['quantity' => 2],
        ]);

        $this->post(URL::signedRoute('order.return', ['order' => $order->number]), [
            'tur' => 'return',
            'gerekce' => 'beden',
            'kalemler' => [$kalem->id => ['sec' => '1', 'adet' => 1]],
        ])->assertSessionHas('hata');

        $this->assertSame(1, ReturnRequest::count());
    }

    public function test_acilmis_talepler_siparis_sayfasinda_listelenir(): void
    {
        $order = $this->teslimEt($this->siparis(2));

        $talep = app(Returns::class)->open($order, 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertOk()
            ->assertSee($talep->number)
            ->assertSee('Talep alındı');
    }

    public function test_reddedilen_talebin_gerekcesi_musteriye_gorunur(): void
    {
        $order = $this->teslimEt($this->siparis(2));

        $talep = app(Returns::class)->open($order, 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        app(Returns::class)->markReceived($talep);
        app(Returns::class)->reject($talep->fresh(), 'Ürün kullanılmış olarak geldi.');

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertOk()
            ->assertSee('Ürün kullanılmış olarak geldi.', false);
    }
}
