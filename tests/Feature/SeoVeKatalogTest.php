<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LegalDocument;
use App\Models\Product;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoVeKatalogTest extends TestCase
{
    use RefreshDatabase;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->urun = Product::create([
            'name' => 'Saten Midi Elbise',
            'base_sku' => 'ZEYS-001',
            'short_description' => 'Işıltılı saten.',
            'material' => '%100 ipek',
            'is_active' => true,
        ]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M', 'L']],
        ], 2890.00);

        $this->urun->fresh()->variants->each->update(['stock' => 4]);
    }

    // --- Sitemap ---

    public function test_sitemap_gecerli_xml_dondurur(): void
    {
        $yanit = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', $yanit->headers->get('Content-Type'));

        $xml = simplexml_load_string($yanit->getContent());
        $this->assertNotFalse($xml, 'Sitemap gecerli XML olmali');
    }

    public function test_sitemap_aktif_urunleri_icerir_pasifleri_icermez(): void
    {
        $gizli = Product::create(['name' => 'Gizli Parça', 'is_active' => false]);

        $icerik = $this->get('/sitemap.xml')->getContent();

        $this->assertStringContainsString('/urun/saten-midi-elbise', $icerik);
        $this->assertStringNotContainsString('/urun/'.$gizli->slug, $icerik);
    }

    public function test_sitemap_sepet_ve_kasa_sayfalarini_icermez(): void
    {
        $icerik = $this->get('/sitemap.xml')->getContent();

        // Kisisel veri ve islem sayfalari dizine girmemeli
        $this->assertStringNotContainsString('/sepet', $icerik);
        $this->assertStringNotContainsString('/odeme', $icerik);
        $this->assertStringNotContainsString('/admin', $icerik);
        $this->assertStringNotContainsString('/siparis', $icerik);
    }

    public function test_sitemap_yururlukteki_yasal_metinleri_icerir(): void
    {
        LegalDocument::publish('kvkk', 'KVKK', 'Metin');

        $this->get('/sitemap.xml')->assertSee('/sayfa/kvkk', false);
    }

    // --- robots.txt ---

    public function test_canli_olmayan_ortamda_tarama_kapali(): void
    {
        /*
         * Hazir olmayan bir site dizine girmemeli. Testler `testing`
         * ortaminda kostugu icin burada tam kapali bekleniyor.
         */
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false);
    }

    public function test_robots_duz_metin_dondurur(): void
    {
        $yanit = $this->get('/robots.txt')->assertOk();

        $this->assertStringContainsString('text/plain', $yanit->headers->get('Content-Type'));
    }

    // --- JSON-LD ---

    public function test_urun_sayfasi_gecerli_jsonld_basar(): void
    {
        $icerik = $this->get('/urun/saten-midi-elbise')->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $icerik, $eslesme);

        $this->assertNotEmpty($eslesme, 'Urun sayfasinda JSON-LD bulunamadi');

        $veri = json_decode($eslesme[1], true);

        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON-LD gecerli olmali');
        $this->assertSame('Product', $veri['@type']);
        $this->assertSame('Saten Midi Elbise', $veri['name']);
        $this->assertSame('Zeys Fashion House', $veri['brand']['name']);
        $this->assertCount(2, $veri['offers']);
    }

    public function test_jsonld_context_anahtari_blade_tarafindan_yenmiyor(): void
    {
        /*
         * GERILEME TESTI: Blade kaynakta gecen "@context" ifadesini kendi
         * yonergesi sanip yiyor. Yapi bu yuzden PHP dizisi olarak kurulup
         * json_encode ile basiliyor.
         */
        $icerik = $this->get('/urun/saten-midi-elbise')->getContent();

        $this->assertStringContainsString('"@context":"https://schema.org"', $icerik);
    }

    public function test_jsonld_stok_durumunu_dogru_bildirir(): void
    {
        $this->urun->fresh()->variants->first()->update(['stock' => 0]);

        $icerik = $this->get('/urun/saten-midi-elbise')->getContent();

        $this->assertStringContainsString('https://schema.org/OutOfStock', $icerik);
        $this->assertStringContainsString('https://schema.org/InStock', $icerik);
    }

    public function test_anasayfa_magaza_jsonld_basar(): void
    {
        $icerik = $this->get('/')->assertOk()->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $icerik, $eslesme);

        $veri = json_decode($eslesme[1] ?? '', true);

        $this->assertSame('ClothingStore', $veri['@type'] ?? null);
        $this->assertSame('Edirne', $veri['address']['addressLocality'] ?? null);
    }

    public function test_canonical_ve_og_etiketleri_var(): void
    {
        $this->get('/urun/saten-midi-elbise')
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('og:title', false)
            ->assertSee('og:type" content="product', false);
    }

    // --- Kategori ---

    public function test_kategori_sayfasi_urunlerini_listeler(): void
    {
        $kategori = Category::create(['name' => 'Elbise', 'is_active' => true]);
        $this->urun->categories()->attach($kategori);

        $this->get('/kategori/elbise')
            ->assertOk()
            ->assertSee('Elbise')
            ->assertSee('Saten Midi Elbise');
    }

    public function test_kategori_alt_kategorilerin_urunlerini_de_gosterir(): void
    {
        $ust = Category::create(['name' => 'Üst Giyim', 'is_active' => true]);
        $alt = Category::create(['name' => 'Gömlek', 'parent_id' => $ust->id, 'is_active' => true]);

        $gomlek = Product::create(['name' => 'Keten Gömlek', 'is_active' => true]);
        $gomlek->categories()->attach($alt);

        $this->get('/kategori/ust-giyim')
            ->assertOk()
            ->assertSee('Keten Gömlek');
    }

    public function test_pasif_kategori_404(): void
    {
        Category::create(['name' => 'Gizli', 'is_active' => false]);

        $this->get('/kategori/gizli')->assertNotFound();
    }

    // --- Arama ---

    public function test_arama_urun_adiyla_bulur(): void
    {
        $this->get('/ara?q=saten')
            ->assertOk()
            ->assertSee('Saten Midi Elbise');
    }

    public function test_arama_buyuk_kucuk_harf_ayrimi_yapmaz(): void
    {
        $this->get('/ara?q=SATEN')->assertOk()->assertSee('Saten Midi Elbise');
    }

    public function test_arama_sku_ile_bulur(): void
    {
        // Magaza personeli elindeki etiketten urunu bulabilmeli
        $sku = $this->urun->fresh()->variants->first()->sku;

        $this->get('/ara?q='.$sku)->assertOk()->assertSee('Saten Midi Elbise');
    }

    public function test_arama_pasif_urunu_gostermez(): void
    {
        $this->urun->update(['is_active' => false]);

        $this->get('/ara?q=saten')
            ->assertOk()
            ->assertSee('sonuç bulunamadı', false);
    }

    public function test_bos_arama_form_gosterir(): void
    {
        $this->get('/ara')
            ->assertOk()
            ->assertSee('Aramak istediğiniz ürünün adını yazın', false);
    }
}
