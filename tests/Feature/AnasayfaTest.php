<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Product;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnasayfaTest extends TestCase
{
    use RefreshDatabase;

    public function test_anasayfa_acilir(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Zeys Fashion House')
            ->assertSee('Zarafet', false);
    }

    public function test_urun_yokken_de_acilir(): void
    {
        // Bos katalog cokmemeli — mağaza ilk kurulduğunda bu hâlde olacak
        $this->get('/')->assertOk()->assertDontSee('Yeni Gelenler');
    }

    public function test_aktif_urunler_listelenir_pasifler_gizlenir(): void
    {
        $gorunur = Product::create(['name' => 'Saten Elbise', 'is_active' => true]);
        $gizli = Product::create(['name' => 'Gizli Parça', 'is_active' => false]);

        app(VariantMatrix::class)->generate($gorunur, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
        ], 2890.00);

        $this->get('/')
            ->assertOk()
            ->assertSee('Saten Elbise')
            ->assertSee('2.890,00 TL')
            ->assertDontSee('Gizli Parça');

        $this->assertTrue($gizli->exists);
    }

    public function test_kargo_esigi_duyuruda_gorunur(): void
    {
        config(['shop.kargo.ucretsiz_esigi' => 1500.00]);

        $this->get('/')->assertOk()->assertSee('1.500 TL ve üzeri kargo ücretsiz', false);
    }

    public function test_koleksiyonlar_urun_sayisiyla_listelenir(): void
    {
        $koleksiyon = Collection::create(['name' => 'Yaz 26', 'is_active' => true]);

        Product::create(['name' => 'A', 'collection_id' => $koleksiyon->id, 'is_active' => true]);
        Product::create(['name' => 'B', 'collection_id' => $koleksiyon->id, 'is_active' => false]);

        // Pasif urun sayilmamali
        $this->get('/')->assertOk()->assertSee('Yaz 26')->assertSee('1 parça', false);
    }

    public function test_yasal_sayfa_baglantilari_alt_bilgide_var(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Mesafeli Satış Sözleşmesi', false)
            ->assertSee('KVKK', false)
            ->assertSee('İade ve Değişim', false);
    }

    public function test_magaza_adresi_ve_instagram_gorunur(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Edirne', false)
            ->assertSee('zeysfashionhouse');
    }
}
