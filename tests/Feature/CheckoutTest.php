<?php

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $varyant;

    protected function setUp(): void
    {
        parent::setUp();

        LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', 'Metin', '2026-09-20.1');
        LegalDocument::publish('on-bilgilendirme', 'Ön Bilgilendirme Formu', 'Metin', '2026-09-20.1');

        $urun = Product::create(['name' => 'Saten Midi Elbise', 'base_sku' => 'ZEYS-001', 'is_active' => true]);

        app(VariantMatrix::class)->generate($urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
        ], 2890.00);

        $this->varyant = $urun->fresh()->variants()->first();
        $this->varyant->update(['stock' => 5]);
    }

    private function sepeteEkle(int $adet = 2): void
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant, $adet);
    }

    /** @return array<string, string> */
    private function form(array $degisiklik = []): array
    {
        return array_merge([
            'ad' => 'Test Müşteri',
            'eposta' => 'test@example.test',
            'telefon' => '5551112233',
            'adres' => 'Cumhuriyet Mah. 1. Sokak No:5',
            'ilce' => 'Merkez',
            'il' => 'Edirne',
            'posta_kodu' => '22000',
            'sozlesme_onay' => '1',
        ], $degisiklik);
    }

    public function test_bos_sepetle_kasaya_girilemez(): void
    {
        $this->get('/odeme')->assertRedirect('/sepet');
    }

    public function test_kasa_formu_ozet_ve_sozlesme_surumunu_gosterir(): void
    {
        $this->sepeteEkle();

        $this->get('/odeme')
            ->assertOk()
            ->assertSee('5.780,00 TL')
            ->assertSee('Mesafeli Satış Sözleşmesi', false)
            ->assertSee('2026-09-20.1');
    }

    public function test_sozlesme_onaylanmadan_siparis_verilemez(): void
    {
        $this->sepeteEkle();

        $this->post('/odeme', $this->form(['sozlesme_onay' => '']))
            ->assertSessionHasErrors('sozlesme_onay');

        $this->assertSame(0, Order::count());
    }

    public function test_eksik_adresle_siparis_verilemez(): void
    {
        $this->sepeteEkle();

        $this->post('/odeme', $this->form(['il' => '', 'ilce' => '']))
            ->assertSessionHasErrors(['il', 'ilce']);

        $this->assertSame(0, Order::count());
    }

    public function test_siparis_olusur_ve_stok_rezerve_edilir(): void
    {
        $this->sepeteEkle(2);

        $this->post('/odeme', $this->form())->assertOk();

        $order = Order::firstOrFail();

        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('reserved', $order->stock_state);
        $this->assertSame('5780.00', $order->grand_total);

        $this->varyant->refresh();
        // Odeme onaylanmadan gercek stok DUSMEZ
        $this->assertSame(5, $this->varyant->stock);
        $this->assertSame(2, $this->varyant->reserved);
    }

    public function test_onaylanan_sozlesme_surumu_ve_ip_kaydedilir(): void
    {
        $this->sepeteEkle();

        $this->post('/odeme', $this->form());

        $order = Order::firstOrFail();

        $this->assertSame('2026-09-20.1', $order->contract_version);
        $this->assertNotNull($order->contract_accepted_at);
        $this->assertNotNull($order->contract_ip);
    }

    public function test_siparis_sonrasi_sepet_bosalir(): void
    {
        $this->sepeteEkle();

        $this->post('/odeme', $this->form());

        $this->assertSame(0, app(Cart::class)->count());
    }

    public function test_adres_siparise_kopyalanir(): void
    {
        $this->sepeteEkle();

        $this->post('/odeme', $this->form());

        $adres = Order::firstOrFail()->shipping_address;

        $this->assertSame('Edirne', $adres['city']);
        $this->assertSame('Merkez', $adres['district']);
        $this->assertSame('Cumhuriyet Mah. 1. Sokak No:5', $adres['line1']);
    }

    public function test_benzetim_odemeyi_tamamlar_ve_stogu_duser(): void
    {
        $this->sepeteEkle(2);
        $this->post('/odeme', $this->form());

        $order = Order::firstOrFail();

        $this->post('/odeme/benzetim', ['order' => $order->number, 'sonuc' => 'basarili'])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('committed', $order->stock_state);

        $this->varyant->refresh();
        $this->assertSame(3, $this->varyant->stock);
        $this->assertSame(0, $this->varyant->reserved);
    }

    public function test_benzetimde_basarisiz_secilince_rezerv_serbest_kalir(): void
    {
        $this->sepeteEkle(2);
        $this->post('/odeme', $this->form());

        $order = Order::firstOrFail();

        $this->post('/odeme/benzetim', ['order' => $order->number, 'sonuc' => 'basarisiz']);

        $order->refresh();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('none', $order->stock_state);

        $this->varyant->refresh();
        $this->assertSame(5, $this->varyant->stock);
        $this->assertSame(0, $this->varyant->reserved);
    }

    public function test_paytr_tanimliysa_benzetim_kapanir(): void
    {
        // Ikinci kilit: kimlik bilgileri varsa benzetim 404
        config([
            'paytr.merchant_id' => '123456',
            'paytr.merchant_key' => 'anahtar',
            'paytr.merchant_salt' => 'tuz',
        ]);

        $this->sepeteEkle();
        $this->post('/odeme', $this->form());

        $order = Order::firstOrFail();

        $this->post('/odeme/benzetim', ['order' => $order->number, 'sonuc' => 'basarili'])
            ->assertNotFound();
    }

    public function test_stok_yetmezse_siparis_gecmez(): void
    {
        $this->sepeteEkle(2);

        // Araya giren baska bir siparis son parcalari kapti
        $this->varyant->update(['stock' => 1]);

        $this->post('/odeme', $this->form())->assertRedirect();

        // Sepette 2 adet istenmisti ama 1 kaldi: kirpilan satir kasayi blokluyor
        $this->assertSame(0, Order::where('status', 'paid')->count());
    }
}
