<?php

namespace Tests\Feature;

use App\Mail\BackInStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockInquiry;
use App\Services\StockAlerts;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class StokBildirimTest extends TestCase
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
    }

    private function varyant(string $etiket): ProductVariant
    {
        return $this->urun->fresh()->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $etiket);
    }

    public function test_tukenmis_varyant_icin_kayit_alinir(): void
    {
        $m = $this->varyant('M'); // stok 0

        $this->post('/stok-haber-ver', [
            'variant_id' => $m->id,
            'eposta' => 'ayse@example.test',
        ])->assertSessionHas('bilgi');

        $this->assertDatabaseHas('stock_inquiries', [
            'product_variant_id' => $m->id,
            'email' => 'ayse@example.test',
            'notified_at' => null,
        ]);
    }

    public function test_stokta_olan_varyant_icin_kayit_alinmaz(): void
    {
        $m = $this->varyant('M');
        $m->update(['stock' => 3]);

        /*
         * Kayit alinsaydi musteri beklemeye baslardi ama urun zaten
         * alinabilir oldugu icin hic bildirim gitmezdi.
         */
        $this->post('/stok-haber-ver', [
            'variant_id' => $m->id,
            'eposta' => 'ayse@example.test',
        ])->assertSessionHas('bilgi');

        $this->assertSame(0, StockInquiry::count());
    }

    public function test_ayni_kisi_ayni_varyanta_iki_kez_kaydolmaz(): void
    {
        $m = $this->varyant('M');

        $this->post('/stok-haber-ver', ['variant_id' => $m->id, 'eposta' => 'ayse@example.test']);
        $this->post('/stok-haber-ver', ['variant_id' => $m->id, 'eposta' => 'AYSE@example.test']);

        $this->assertSame(1, StockInquiry::count());
    }

    public function test_stok_gelince_bekleyene_eposta_gider(): void
    {
        $m = $this->varyant('M');
        app(StockAlerts::class)->subscribe($m, 'ayse@example.test');

        $m->update(['stock' => 4]);

        Mail::assertSent(BackInStock::class, fn (BackInStock $mail) => $mail->hasTo('ayse@example.test')
            && $mail->variant->is($m));

        $this->assertNotNull(StockInquiry::first()->notified_at);
    }

    public function test_bildirim_bir_kez_gider(): void
    {
        $m = $this->varyant('M');
        app(StockAlerts::class)->subscribe($m, 'ayse@example.test');

        $m->update(['stock' => 4]);
        // Sonraki kayitlar (fiyat duzeltmesi, etiket degisimi) posta gondermemeli
        $m->fresh()->update(['price' => 3100]);
        $m->fresh()->update(['stock' => 9]);

        Mail::assertSent(BackInStock::class, 1);
    }

    public function test_baska_bedenin_stogu_gelince_bildirim_gitmez(): void
    {
        /*
         * Musteri "M" bekliyor; "L" gelince haber vermek yanlis olur ve
         * guven kaybettirir. Kayit URUNE degil VARYANTA bagli.
         */
        $m = $this->varyant('M');
        $l = $this->varyant('L');

        app(StockAlerts::class)->subscribe($m, 'ayse@example.test');

        $l->update(['stock' => 5]);

        Mail::assertNotSent(BackInStock::class);
        $this->assertNull(StockInquiry::first()->notified_at);
    }

    public function test_rezerve_yuzunden_satilabilir_stok_sifirsa_bildirim_gitmez(): void
    {
        $m = $this->varyant('M');
        app(StockAlerts::class)->subscribe($m, 'ayse@example.test');

        // Stok 2 ama tamami rezerve — gercekte satilabilir degil
        $m->update(['stock' => 2, 'reserved' => 2]);

        Mail::assertNotSent(BackInStock::class);
    }

    public function test_rezerv_serbest_kalinca_bildirim_gider(): void
    {
        $m = $this->varyant('M');
        $m->update(['stock' => 2, 'reserved' => 2]);

        app(StockAlerts::class)->subscribe($m->fresh(), 'ayse@example.test');

        // Odemesi dusen siparisin rezervi serbest kaldi
        $m->fresh()->update(['reserved' => 0]);

        Mail::assertSent(BackInStock::class);
    }

    public function test_pasif_urun_icin_kayit_alinmaz(): void
    {
        $m = $this->varyant('M');
        $this->urun->update(['is_active' => false]);

        $this->post('/stok-haber-ver', [
            'variant_id' => $m->id,
            'eposta' => 'ayse@example.test',
        ])->assertSessionHas('hata');

        $this->assertSame(0, StockInquiry::count());
    }

    public function test_gecersiz_eposta_reddedilir(): void
    {
        $m = $this->varyant('M');

        $this->post('/stok-haber-ver', [
            'variant_id' => $m->id,
            'eposta' => 'bozuk-adres',
        ])->assertSessionHasErrors('eposta');
    }
}
