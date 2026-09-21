<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\User;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PressMart düzenine geçişle gelen davranışlar: sıralama, fiyat süzgeci,
 * kategori içinde arama, kart rozetleri, menüler, ana sayfa bölümleri.
 */
class TasarimTest extends TestCase
{
    use RefreshDatabase;

    private function urun(string $ad, float $fiyat, array $alanlar = []): Product
    {
        $urun = Product::create(array_merge(['name' => $ad, 'is_active' => true], $alanlar));

        app(VariantMatrix::class)->generate($urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
        ], $fiyat);

        $urun->variants()->update(['stock' => 3]);
        $urun->variants->first()->save(); // gözlemci önbelleği tazelesin

        return $urun->fresh();
    }

    private function sira(string $html, array $adlar): array
    {
        $konumlar = [];
        foreach ($adlar as $ad) {
            $konumlar[$ad] = strpos($html, $ad);
        }
        asort($konumlar);

        return array_keys($konumlar);
    }

    public function test_magaza_fiyata_gore_siralanir(): void
    {
        $this->urun('Pahalı Ceket', 3000);
        $this->urun('Ucuz Bluz', 900);
        $this->urun('Orta Etek', 1500);

        $artan = $this->get('/koleksiyonlar?sirala=fiyat-artan')->assertOk()->getContent();
        $this->assertSame(['Ucuz Bluz', 'Orta Etek', 'Pahalı Ceket'], $this->sira($artan, ['Pahalı Ceket', 'Ucuz Bluz', 'Orta Etek']));

        $azalan = $this->get('/koleksiyonlar?sirala=fiyat-azalan')->getContent();
        $this->assertSame(['Pahalı Ceket', 'Orta Etek', 'Ucuz Bluz'], $this->sira($azalan, ['Pahalı Ceket', 'Ucuz Bluz', 'Orta Etek']));
    }

    public function test_gecersiz_siralama_varsayilana_duser(): void
    {
        $this->urun('Bluz', 900);

        $this->get('/koleksiyonlar?sirala=DROP%20TABLE')->assertOk()->assertSee('Bluz');
    }

    public function test_fiyat_suzgeci(): void
    {
        $this->urun('Pahalı Ceket', 3000);
        $this->urun('Ucuz Bluz', 900);

        $this->get('/koleksiyonlar?fiyat_min=1000&fiyat_max=5000')
            ->assertOk()
            ->assertSee('Pahalı Ceket')
            ->assertDontSee('Ucuz Bluz');

        // Bozuk değer süzgeci bozmaz, yok sayılır
        $this->get('/koleksiyonlar?fiyat_min=abc')->assertOk()->assertSee('Ucuz Bluz');
    }

    public function test_indirim_suzgeci_yalniz_indirimdekileri_gosterir_ve_kartta_rozet_cikar(): void
    {
        $indirimli = $this->urun('İndirimli Elbise', 1500);
        $indirimli->variants()->update(['compare_at_price' => 2000]);
        $this->urun('Normal Etek', 1200);

        $this->get('/koleksiyonlar?sirala=indirim')
            ->assertOk()
            ->assertSee('İndirimli Elbise')
            ->assertDontSee('Normal Etek')
            ->assertSee('%25 İndirim', false)
            ->assertSee('2.000,00 TL', false);
    }

    public function test_eski_fiyat_satistan_dusukse_indirim_yazilmaz(): void
    {
        $urun = $this->urun('Yanlış Girilmiş', 1500);
        $urun->variants()->update(['compare_at_price' => 1000]);

        $this->get('/koleksiyonlar')->assertOk()->assertDontSee('İndirim</span>', false);
    }

    public function test_baslik_aramasi_kategori_ile_daraltilir(): void
    {
        $elbise = Category::create(['name' => 'Elbise', 'is_active' => true]);
        $etek = Category::create(['name' => 'Etek', 'is_active' => true]);

        $this->urun('Keten Elbise', 1500)->categories()->attach($elbise);
        $this->urun('Keten Etek', 1200)->categories()->attach($etek);

        $this->get('/ara?q=keten&kategori='.$elbise->slug)
            ->assertOk()
            ->assertSee('Keten Elbise')
            ->assertDontSee('Keten Etek');

        $this->get('/ara?q=keten')->assertSee('Keten Elbise')->assertSee('Keten Etek');
    }

    public function test_menu_ve_yan_sutunda_kategoriler_gorunur(): void
    {
        $ust = Category::create(['name' => 'Üst Giyim', 'is_active' => true]);
        Category::create(['name' => 'Gömlek', 'parent_id' => $ust->id, 'is_active' => true]);
        Category::create(['name' => 'Gizli Kategori', 'is_active' => false]);
        Collection::create(['name' => 'Yaz 26', 'is_active' => true]);

        $sayfa = $this->get('/koleksiyonlar')->assertOk();

        $sayfa->assertSee('kategori-menu-liste', false)
            ->assertSee('Üst Giyim')
            ->assertSee('Gömlek')
            ->assertSee('Yaz 26')
            ->assertDontSee('Gizli Kategori');
    }

    public function test_sayfalama_kendi_gorunumuyle_cizilir(): void
    {
        foreach (range(1, 25) as $i) {
            Product::create(['name' => 'Ürün '.$i, 'is_active' => true]);
        }

        $this->get('/koleksiyonlar')
            ->assertOk()
            ->assertSee('class="sayfalar"', false)
            ->assertSee('sayfa-aktif', false);
    }

    public function test_hesap_sayfalarinda_yan_menu_var(): void
    {
        $musteri = User::factory()->create(['role' => 'customer', 'name' => 'Ayşe Yılmaz']);

        foreach (['/hesap', '/hesap/adresler', '/hesap/verilerim'] as $yol) {
            $this->actingAs($musteri)->get($yol)
                ->assertOk()
                ->assertSee('hesap-menu-aktif', false)
                ->assertSee('Ayşe Yılmaz')
                ->assertSee('Çıkış yap');
        }
    }

    public function test_ana_sayfa_bolumleri_veriye_gore_cizilir(): void
    {
        $kategori = Category::create(['name' => 'Elbise', 'is_active' => true]);
        $urun = $this->urun('Saten Elbise', 2890, ['is_featured' => true]);
        $urun->categories()->attach($kategori);

        $this->get('/')
            ->assertOk()
            ->assertSee('kategori-daire', false)
            ->assertSee('Moda Ürünleri')
            ->assertSee('Yeni Gelenler')
            // Satış ve yorum yoksa bu sekmeler çizilmez
            ->assertDontSee('id="sekme-cok-satan"', false)
            ->assertDontSee('id="sekme-begenilen"', false)
            // İndirim yoksa fırsat bölümü yok
            ->assertDontSee('Fırsat Ürünleri');
    }

    public function test_urun_sayfasinda_sekmeler_ve_benzer_urunler(): void
    {
        $kol = Collection::create(['name' => 'Yaz 26', 'is_active' => true]);
        $urun = $this->urun('Keten Gömlek', 1490, ['collection_id' => $kol->id, 'material' => '%100 keten']);
        $this->urun('Keten Pantolon', 1990, ['collection_id' => $kol->id]);

        $this->get('/urun/'.$urun->slug)
            ->assertOk()
            ->assertSee('Açıklama')
            ->assertSee('Ek Bilgi')
            ->assertSee('Değerlendirmeler (0)')
            ->assertSee('%100 keten')
            ->assertSee('Benzer ürünler')
            ->assertSee('Keten Pantolon');
    }
}
