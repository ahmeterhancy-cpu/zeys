<?php

namespace Tests\Feature;

use App\Filament\Resources\Ozellikler\Pages\EditOzellik;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Order;
use App\Models\Ozellik;
use App\Models\OzellikDegeri;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\UrunVaryantlari;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * WooCommerce tarzı varyant yönetimi: ortak özellik kütüphanesi,
 * kaydedince kendiliğinden oluşan kombinasyonlar, satır içi düzenleme.
 */
class VaryantYonetimiTest extends TestCase
{
    use RefreshDatabase;

    private User $yonetici;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yonetici = User::factory()->create(['role' => 'admin']);
    }

    private function ozellik(string $ad): Ozellik
    {
        return Ozellik::where('ad', $ad)->firstOrFail(); // migration varsayılanları
    }

    /** @return list<string> */
    private function degerler(string $ozellik, array $adlar): array
    {
        return OzellikDegeri::where('ozellik_id', $this->ozellik($ozellik)->id)
            ->whereIn('deger', $adlar)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    private function etiketler(Product $urun, bool $yalnizAktif = false): array
    {
        return $urun->fresh()->variants()->with('optionValues.option')->get()
            ->when($yalnizAktif, fn ($c) => $c->where('is_active', true))
            ->map->label->sort()->values()->all();
    }

    public function test_kutuphane_varsayilanlari_var(): void
    {
        $this->assertContains('M', $this->ozellik('Beden')->degerler->pluck('deger'));
        $this->assertSame('#1c1a15', $this->ozellik('Renk')->degerler->firstWhere('deger', 'Siyah')->renk_kodu);
    }

    public function test_urun_olustururken_kombinasyonlar_tek_adimda_olusur(): void
    {
        Livewire::actingAs($this->yonetici)->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Saten Gömlek',
                'base_sku' => 'ZEYS-100',
                'is_active' => true,
                'eksenler' => [
                    ['ozellik_id' => (string) $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M', 'L'])],
                    ['ozellik_id' => (string) $this->ozellik('Renk')->id, 'degerler' => $this->degerler('Renk', ['Siyah', 'Bej'])],
                ],
                'varsayilan_fiyat' => 1890,
                'varsayilan_eski_fiyat' => 2290,
                'varsayilan_stok' => 4,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $urun = Product::where('name', 'Saten Gömlek')->firstOrFail();
        $varyantlar = $urun->variants;

        $this->assertCount(6, $varyantlar, '3 beden × 2 renk');
        $this->assertSame(['1890.00'], $varyantlar->pluck('price')->unique()->values()->all());
        $this->assertSame(['2290.00'], $varyantlar->pluck('compare_at_price')->unique()->values()->all());
        $this->assertSame([4], $varyantlar->pluck('stock')->unique()->values()->all());
        $this->assertSame(24, $urun->fresh()->total_stock);

        // Ürün tarafı kütüphaneye bağlı
        $this->assertSame($this->ozellik('Renk')->id, $urun->options->firstWhere('name', 'Renk')->ozellik_id);
        $this->assertContains('ZEYS-100-M-SIYAH', $varyantlar->pluck('sku')->all());
    }

    public function test_duzenlerken_deger_eklenir_mevcut_fiyat_ve_stok_korunur(): void
    {
        $urun = Product::create(['name' => 'Triko', 'base_sku' => 'ZEYS-101', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M'])],
        ], ['fiyat' => 1000, 'stok' => 2]);

        ProductVariant::query()->update(['price' => 1500, 'stock' => 9]);

        $sayfa = Livewire::actingAs($this->yonetici)->test(EditProduct::class, ['record' => $urun->id]);

        // Form mevcut seçimle açılır (tekrarlayıcı satırları UUID anahtarlı)
        $eksenler = array_values($sayfa->get('data.eksenler'));
        $this->assertCount(1, $eksenler);
        $this->assertEquals($this->ozellik('Beden')->id, $eksenler[0]['ozellik_id']);
        $this->assertEqualsCanonicalizing($this->degerler('Beden', ['S', 'M']), $eksenler[0]['degerler']);

        $sayfa
            ->fillForm([
                'eksenler' => [['ozellik_id' => (string) $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M', 'L'])]],
                'varsayilan_fiyat' => 1500,
                'varsayilan_stok' => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $v = $urun->fresh()->variants()->with('optionValues.option')->get()->keyBy('label');

        $this->assertSame(['L', 'M', 'S'], $this->etiketler($urun));
        $this->assertSame(9, $v['S']->stock, 'Var olan stok korunmalı');
        $this->assertSame(1, $v['L']->stock, 'Yeni kombinasyona başlangıç stoğu');
    }

    public function test_cikarilan_deger_satilmamissa_silinir_satilmissa_satistan_kalkar(): void
    {
        $urun = Product::create(['name' => 'Etek', 'base_sku' => 'ZEYS-102', 'is_active' => true]);
        $servis = app(UrunVaryantlari::class);
        $beden = ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M', 'L'])];

        $servis->esitle($urun, [$beden], ['fiyat' => 900, 'stok' => 3]);

        // L satılmış olsun
        $l = $urun->fresh()->variants()->with('optionValues.option')->get()->firstWhere('label', 'L');
        $siparis = Order::create(['number' => 'ZEY-1', 'customer_name' => 'A', 'customer_email' => 'a@x.test', 'customer_phone' => '1', 'shipping_address' => [], 'grand_total' => 900]);
        $siparis->items()->create(['product_id' => $urun->id, 'product_variant_id' => $l->id, 'name' => 'Etek', 'unit_price' => 900, 'quantity' => 1, 'line_total' => 900]);

        $sonuc = $servis->esitle($urun, [['ozellik_id' => $beden['ozellik_id'], 'degerler' => $this->degerler('Beden', ['S'])]]);

        $this->assertSame(1, $sonuc['silinen'], 'M hiç satılmadı → silinir');
        $this->assertSame(1, $sonuc['kaldirilan'], 'L satıldı → satıştan kalkar');
        $this->assertFalse($l->fresh()->is_active);
        $this->assertSame(['S'], $this->etiketler($urun, true));

        // Vitrinde L görünmez
        $this->get('/urun/'.$urun->slug)->assertOk()->assertDontSee('value="'.$l->optionValues->first()->id.'"', false);

        // Formda L seçili gelmez
        $this->assertSame([$this->degerler('Beden', ['S'])[0]], $servis->formDurumu($urun->fresh())[0]['degerler']);

        // L geri eklenirse eski (satılmış) varyant satışa döner
        $servis->esitle($urun, [$beden]);
        $this->assertTrue($l->fresh()->is_active);
        $this->assertSame(['L', 'M', 'S'], $this->etiketler($urun, true));
    }

    public function test_elle_kapatilan_varyant_kayitta_acilmaz(): void
    {
        $urun = Product::create(['name' => 'Bluz', 'base_sku' => 'ZEYS-103', 'is_active' => true]);
        $servis = app(UrunVaryantlari::class);
        $eksenler = [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M'])],
            ['ozellik_id' => $this->ozellik('Renk')->id, 'degerler' => $this->degerler('Renk', ['Siyah', 'Bej'])],
        ];
        $servis->esitle($urun, $eksenler, ['fiyat' => 500]);

        $kapali = $urun->fresh()->variants()->with('optionValues.option')->get()->firstWhere('label', 'S / Siyah');
        $kapali->update(['is_active' => false]);

        $servis->esitle($urun, $eksenler);

        $this->assertFalse($kapali->fresh()->is_active);
    }

    public function test_ozellik_tamamen_kaldirilirsa_tek_eksenli_kombinasyonlar_kalir(): void
    {
        $urun = Product::create(['name' => 'Şal', 'base_sku' => 'ZEYS-104', 'is_active' => true]);
        $servis = app(UrunVaryantlari::class);
        $renk = ['ozellik_id' => $this->ozellik('Renk')->id, 'degerler' => $this->degerler('Renk', ['Siyah', 'Bej'])];

        $servis->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M'])],
            $renk,
        ], ['fiyat' => 300]);

        $servis->esitle($urun, [$renk], ['fiyat' => 300]);

        $this->assertSame(['Bej', 'Siyah'], $this->etiketler($urun));
        $this->assertSame(['Renk'], $urun->fresh()->options->pluck('name')->all(), 'Boş Beden ekseni temizlenmeli');
    }

    public function test_kutuphanede_ad_degisince_urunlerde_de_degisir(): void
    {
        $urun = Product::create(['name' => 'Ceket', 'base_sku' => 'ZEYS-105', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Renk')->id, 'degerler' => $this->degerler('Renk', ['Siyah'])],
        ], ['fiyat' => 100]);

        OzellikDegeri::where('deger', 'Siyah')->first()->update(['deger' => 'Kömür', 'renk_kodu' => '#222222']);

        $this->assertSame(['Kömür'], $this->etiketler($urun));
        $this->assertSame('#222222', $urun->fresh()->options->first()->values->first()->color_hex);
    }

    public function test_urunde_kullanilan_deger_kutuphaneden_silinemez(): void
    {
        $urun = Product::create(['name' => 'Elbise', 'base_sku' => 'ZEYS-106', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['M'])],
        ], ['fiyat' => 100]);

        $m = OzellikDegeri::where('deger', 'M')->first();

        Livewire::actingAs($this->yonetici)->test(EditOzellik::class, ['record' => $this->ozellik('Beden')->id])
            ->callAction(TestAction::make('delete')->schemaComponent('degerler')->arguments(['item' => 'record-'.$m->id]))
            ->call('save');

        $this->assertModelExists($m);
        $this->assertSame(['M'], $this->etiketler($urun));
    }

    public function test_varyant_tablosunda_satir_ici_duzenleme_ve_toplu_islemler(): void
    {
        $urun = Product::create(['name' => 'Pantolon', 'base_sku' => 'ZEYS-107', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M'])],
            ['ozellik_id' => $this->ozellik('Renk')->id, 'degerler' => $this->degerler('Renk', ['Siyah', 'Bej'])],
        ], ['fiyat' => 1000, 'stok' => 5]);

        $v = fn (string $etiket) => $urun->fresh()->variants()->with('optionValues.option')->get()->firstWhere('label', $etiket);
        $tablo = fn () => Livewire::actingAs($this->yonetici)->test(VariantsRelationManager::class, ['ownerRecord' => $urun->fresh(), 'pageClass' => EditProduct::class]);

        // Satır içi fiyat ve stok
        $tablo()->call('updateTableColumnState', 'price', (string) $v('S / Siyah')->id, '1250');
        $tablo()->call('updateTableColumnState', 'stock', (string) $v('S / Siyah')->id, '11');
        $this->assertSame('1250.00', $v('S / Siyah')->price);
        $this->assertSame(11, $v('S / Siyah')->stock);

        // Değer süzgeci: yalnız Siyah
        $siyahId = $urun->fresh()->options->firstWhere('name', 'Renk')->values->firstWhere('value', 'Siyah')->id;
        $tablo()->filterTable('deger', [$siyahId])
            ->assertCanSeeTableRecords([$v('S / Siyah'), $v('M / Siyah')])
            ->assertCanNotSeeTableRecords([$v('S / Bej')]);

        // Yüzde indirim, eski fiyat korunarak
        $tablo()->callTableBulkAction('fiyatYuzde', [$v('M / Bej')], ['yon' => 'azalt', 'yuzde' => 20, 'eskiyi_koru' => true])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame('800.00', $v('M / Bej')->price);
        $this->assertSame('1000.00', $v('M / Bej')->compare_at_price);

        // Stok ekle
        $tablo()->callTableBulkAction('stokAta', [$v('S / Bej'), $v('M / Bej')], ['islem' => 'ekle', 'stok' => 3]);
        $this->assertSame(8, $v('S / Bej')->stock);

        // Satıştan kaldır
        $tablo()->callTableBulkAction('satistanKaldir', [$v('S / Bej')]);
        $this->assertFalse($v('S / Bej')->is_active);
    }

    public function test_stok_satir_icinde_rezervin_altina_inemez(): void
    {
        $urun = Product::create(['name' => 'Gömlek', 'base_sku' => 'ZEYS-108', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['M'])],
        ], ['fiyat' => 100, 'stok' => 5]);

        $varyant = $urun->variants()->first();
        $varyant->update(['reserved' => 3]);

        Livewire::actingAs($this->yonetici)
            ->test(VariantsRelationManager::class, ['ownerRecord' => $urun, 'pageClass' => EditProduct::class])
            ->call('updateTableColumnState', 'stock', (string) $varyant->id, '1');

        $this->assertSame(5, $varyant->fresh()->stock);
    }

    public function test_personel_satir_icinde_fiyat_degistiremez(): void
    {
        $urun = Product::create(['name' => 'Kazak', 'base_sku' => 'ZEYS-109', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['M'])],
        ], ['fiyat' => 700]);

        $varyant = $urun->variants()->first();

        Livewire::actingAs(User::factory()->create(['role' => 'staff']))
            ->test(VariantsRelationManager::class, ['ownerRecord' => $urun, 'pageClass' => EditProduct::class])
            ->call('updateTableColumnState', 'price', (string) $varyant->id, '1')
            ->assertTableBulkActionHidden('fiyatAta');

        $this->assertSame('700.00', $varyant->fresh()->price);
    }

    public function test_yeni_ozellik_ve_deger_panelden_eklenir(): void
    {
        $this->actingAs($this->yonetici)->get('/admin/ozellikler')->assertOk()->assertSee('Beden')->assertSee('Siyah');
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/admin/ozellikler')->assertForbidden();
    }

    public function test_varyant_tablosu_ve_galeri_kendi_sekmelerinde(): void
    {
        $urun = Product::create(['name' => 'Tunik', 'base_sku' => 'ZEYS-110', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['S', 'M'])],
        ], ['fiyat' => 990]);

        $html = $this->actingAs($this->yonetici)
            ->get('/admin/products/'.$urun->id.'/edit?tab=varyantlar')
            ->assertOk()
            ->assertSee('Kombinasyonlar — fiyat, stok, görsel')
            ->assertSee('ZEYS-110-S')
            ->assertSee('Galeri')
            ->getContent();

        // Sekmelerin dışında (sayfanın altında) ikinci bir varyant tablosu olmamalı
        $this->assertSame(1, substr_count($html, 'ZEYS-110-S'));
    }

    public function test_olusturunca_varyantlar_sekmesine_gider(): void
    {
        Livewire::actingAs($this->yonetici)->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Yelek',
                'is_active' => true,
                'eksenler' => [['ozellik_id' => (string) $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['M'])]],
                'varsayilan_fiyat' => 500,
            ])
            ->call('create')
            ->assertRedirectContains('tab=varyantlar');
    }

    public function test_varyant_degismeyen_kayitta_sayfada_kalinir(): void
    {
        $urun = Product::create(['name' => 'Hırka', 'base_sku' => 'ZEYS-111', 'is_active' => true]);
        app(UrunVaryantlari::class)->esitle($urun, [
            ['ozellik_id' => $this->ozellik('Beden')->id, 'degerler' => $this->degerler('Beden', ['M'])],
        ], ['fiyat' => 500]);

        Livewire::actingAs($this->yonetici)->test(EditProduct::class, ['record' => $urun->id])
            ->fillForm(['name' => 'Uzun Hırka'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $this->assertSame('Uzun Hırka', $urun->fresh()->name);
        $this->assertSame(1, $urun->variants()->count());
    }
}
