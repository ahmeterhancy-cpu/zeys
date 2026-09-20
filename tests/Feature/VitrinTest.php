<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VitrinTest extends TestCase
{
    use RefreshDatabase;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        $koleksiyon = Collection::create(['name' => 'Yaz 26', 'is_active' => true]);

        $this->urun = Product::create([
            'name' => 'Saten Midi Elbise',
            'base_sku' => 'ZEYS-001',
            'collection_id' => $koleksiyon->id,
            'short_description' => 'Işıltılı saten.',
            'model_note' => 'Model 1.75 m, M beden giyiyor.',
            'is_active' => true,
        ]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['S', 'M']],
            'Renk' => ['kind' => 'color', 'values' => [
                ['value' => 'Siyah', 'color_hex' => '#111111'],
                ['value' => 'Bej', 'color_hex' => '#d8c9ae'],
            ]],
        ], 2890.00);

        $this->urun->fresh()->variants->each->update(['stock' => 5]);
    }

    private function varyant(string $etiket): ProductVariant
    {
        return $this->urun->fresh()->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $etiket);
    }

    public function test_urun_sayfasi_acilir(): void
    {
        $this->get('/urun/saten-midi-elbise')
            ->assertOk()
            ->assertSee('Saten Midi Elbise')
            ->assertSee('2.890,00 TL')
            ->assertSee('Model 1.75 m', false)
            ->assertSee('Beden')
            ->assertSee('Renk');
    }

    public function test_pasif_urun_sayfasi_404(): void
    {
        $this->urun->update(['is_active' => false]);

        $this->get('/urun/saten-midi-elbise')->assertNotFound();
    }

    public function test_varyant_verisi_sayfaya_gomulur(): void
    {
        $yanit = $this->get('/urun/saten-midi-elbise')->assertOk();

        $icerik = $yanit->getContent();

        // Secici bu veriye dayaniyor; fiyat ve stok gomulu olmali
        $this->assertStringContainsString('varyant-verisi', $icerik);
        $this->assertStringContainsString('"stok":5', $icerik);
        $this->assertStringContainsString('ZEYS-001-S-SIYAH', $icerik);
    }

    public function test_beden_tablosu_yoksa_dugme_cikmaz(): void
    {
        $this->get('/urun/saten-midi-elbise')
            ->assertOk()
            ->assertDontSee('Beden tablosu');
    }

    public function test_sepete_ekleme_calisir(): void
    {
        $varyant = $this->varyant('M / Bej');

        $this->post('/sepet/ekle', ['variant_id' => $varyant->id, 'quantity' => 2])
            ->assertRedirect('/sepet');

        $this->assertSame(2, app(Cart::class)->count());
    }

    public function test_tukenmis_varyant_sepete_eklenemez(): void
    {
        $varyant = $this->varyant('M / Bej');
        $varyant->update(['stock' => 0]);

        $this->post('/sepet/ekle', ['variant_id' => $varyant->id, 'quantity' => 1])
            ->assertSessionHas('hata');

        $this->assertSame(0, app(Cart::class)->count());
    }

    public function test_pasif_urunun_varyanti_sepete_eklenemez(): void
    {
        $varyant = $this->varyant('M / Bej');
        $this->urun->update(['is_active' => false]);

        $this->post('/sepet/ekle', ['variant_id' => $varyant->id, 'quantity' => 1])
            ->assertSessionHas('hata');

        $this->assertSame(0, app(Cart::class)->count());
    }

    public function test_sepet_sayfasi_varyant_etiketini_gosterir(): void
    {
        $varyant = $this->varyant('M / Bej');
        $this->post('/sepet/ekle', ['variant_id' => $varyant->id, 'quantity' => 1]);

        $this->get('/sepet')
            ->assertOk()
            ->assertSee('Saten Midi Elbise')
            ->assertSee('M / Bej')
            ->assertSee($varyant->sku);
    }

    public function test_bos_sepet_mesaji(): void
    {
        $this->get('/sepet')->assertOk()->assertSee('Sepetiniz henüz boş', false);
    }

    public function test_tukenen_satir_sepette_kalir_ve_uyarir(): void
    {
        $varyant = $this->varyant('M / Bej');
        $this->post('/sepet/ekle', ['variant_id' => $varyant->id, 'quantity' => 2]);

        // Arada stok bitti
        $varyant->update(['stock' => 0]);

        $this->get('/sepet')
            ->assertOk()
            // Sessizce silinmemeli
            ->assertSee('M / Bej')
            ->assertSee('Bu beden tükendi', false);
    }

    public function test_koleksiyon_sayfasi_kendi_urunlerini_listeler(): void
    {
        $baska = Collection::create(['name' => 'Basic', 'is_active' => true]);
        Product::create(['name' => 'Başka Parça', 'collection_id' => $baska->id, 'is_active' => true]);

        $this->get('/koleksiyon/yaz-26')
            ->assertOk()
            ->assertSee('Saten Midi Elbise')
            ->assertDontSee('Başka Parça');
    }

    public function test_pasif_koleksiyon_404(): void
    {
        Collection::where('slug', 'yaz-26')->update(['is_active' => false]);

        $this->get('/koleksiyon/yaz-26')->assertNotFound();
    }

    public function test_yasal_sayfa_yururlukteki_surumu_gosterir(): void
    {
        LegalDocument::publish('kvkk', 'KVKK Aydınlatma Metni', 'Eski metin');
        $yeni = LegalDocument::publish('kvkk', 'KVKK Aydınlatma Metni', 'Yeni metin');

        $this->get('/sayfa/kvkk')
            ->assertOk()
            ->assertSee('Yeni metin')
            ->assertDontSee('Eski metin')
            ->assertSee($yeni->version);
    }

    public function test_olmayan_yasal_sayfa_404(): void
    {
        $this->get('/sayfa/uydurma-metin')->assertNotFound();
    }

    public function test_iletisim_sayfasi_adresi_gosterir(): void
    {
        $this->get('/iletisim')
            ->assertOk()
            ->assertSee('Edirne', false)
            ->assertSee('zeysfashionhouse');
    }

    public function test_renk_ekseni_kart_uzerinde_nokta_olarak_cikar(): void
    {
        // Renk noktalari eksen ADINA degil kind=color'a bakmali
        $this->assertCount(2, $this->urun->fresh()->renkler);

        $this->get('/')->assertOk()->assertSee('#d8c9ae', false);
    }

    public function test_renk_degeri_kind_degisince_nokta_kaybolur(): void
    {
        ProductOptionValue::query()->update(['color_hex' => null]);
        $this->urun->options()->where('name', 'Renk')->update(['kind' => 'text']);

        $this->assertCount(0, $this->urun->fresh()->renkler);
    }
}
