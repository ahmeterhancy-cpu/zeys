<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Mail\BackInStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockInquiry;
use App\Models\User;
use App\Services\UrunCsv;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class UrunCsvTest extends TestCase
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

    /** @return array{0: ProductVariant, 1: ProductVariant} */
    private function varyantlar(): array
    {
        $v = $this->urun->variants()->with('optionValues.option')->get();

        return [$v->firstWhere('label', 'M'), $v->firstWhere('label', 'L')];
    }

    private function csv(array $satirlar, string $ayrac = ';'): string
    {
        return "\xEF\xBB\xBF".implode("\r\n", array_map(fn ($s) => implode($ayrac, $s), $satirlar));
    }

    public function test_disa_aktarim_turkce_excel_bicimindedir(): void
    {
        [$m] = $this->varyantlar();
        $m->update(['stock' => 7, 'compare_at_price' => 3400]);

        $icerik = app(UrunCsv::class)->disaAktar();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $icerik, 'BOM yoksa Excel Türkçe karakterleri bozar');
        $this->assertStringContainsString('sku;urun;varyant;barkod;fiyat;eski_fiyat;stok;rezerve;aktif', $icerik);
        $this->assertStringContainsString($m->sku.';"Saten Midi Elbise";M;;2890,00;3400,00;7;0;evet', $icerik);
    }

    public function test_disa_aktarilan_dosya_geri_yuklenince_hicbir_sey_degismez(): void
    {
        $sonuc = app(UrunCsv::class)->iceAktar(app(UrunCsv::class)->disaAktar());

        $this->assertSame([], $sonuc['hatalar']);
        $this->assertSame(0, $sonuc['guncellenen']);
        $this->assertSame(2, $sonuc['degismeyen']);
    }

    public function test_fiyat_ve_stok_sku_ile_guncellenir(): void
    {
        [$m, $l] = $this->varyantlar();

        $sonuc = app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'fiyat', 'eski_fiyat', 'stok'],
            [$m->sku, '"2.590,00"', '3.100', '12'],
            [$l->sku, '', '', '4'],
        ]));

        $this->assertSame([], $sonuc['hatalar']);
        $this->assertSame(2, $sonuc['guncellenen']);

        $this->assertSame('2590.00', $m->fresh()->price);
        $this->assertSame('3100.00', $m->fresh()->compare_at_price);
        $this->assertSame(12, $m->fresh()->stock);
        $this->assertSame('2890.00', $l->fresh()->price, 'Boş hücre değiştirilmemeli');
        $this->assertSame(4, $l->fresh()->stock);

        // Ürün önbelleği (gözlemci) tazelenmiş olmalı
        $this->assertSame(16, $this->urun->fresh()->total_stock);
        $this->assertSame('2590.00', $this->urun->fresh()->min_price);
    }

    public function test_virgulle_ayrilmis_ve_ingilizce_ondalik_da_okunur(): void
    {
        [$m] = $this->varyantlar();

        app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'fiyat'],
            [$m->sku, '2450.50'],
        ], ','));

        $this->assertSame('2450.50', $m->fresh()->price);
    }

    public function test_tek_hatali_satir_tum_dosyayi_durdurur(): void
    {
        [$m, $l] = $this->varyantlar();

        $sonuc = app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'stok'],
            [$m->sku, '9'],
            [$l->sku, '3,5'],
            ['YOK-123', '1'],
            [$m->sku, '2'],
        ]));

        $this->assertCount(3, $sonuc['hatalar']);
        $this->assertStringContainsString('Satır 3', $sonuc['hatalar'][0]);
        $this->assertStringContainsString('YOK-123 bulunamadı', $sonuc['hatalar'][1]);
        $this->assertStringContainsString('ikinci kez', $sonuc['hatalar'][2]);

        $this->assertSame(0, $m->fresh()->stock, 'Hatalı dosyada geçerli satırlar da uygulanmamalı');
    }

    public function test_stok_rezervin_altina_indirilemez(): void
    {
        [$m] = $this->varyantlar();
        $m->update(['stock' => 5, 'reserved' => 3]);

        $sonuc = app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'stok'],
            [$m->sku, '2'],
        ]));

        $this->assertStringContainsString('3 adet ödemesi beklenen', $sonuc['hatalar'][0]);
        $this->assertSame(5, $m->fresh()->stock);
    }

    public function test_eski_fiyat_satis_fiyatindan_buyuk_olmali_ve_tire_ile_kaldirilir(): void
    {
        [$m] = $this->varyantlar();

        $hata = app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'eski_fiyat'],
            [$m->sku, '2000'],
        ]));
        $this->assertNotEmpty($hata['hatalar']);

        $m->update(['compare_at_price' => 3400]);

        app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'eski_fiyat'],
            [$m->sku, '-'],
        ]));
        $this->assertNull($m->fresh()->compare_at_price);
    }

    public function test_aktiflik_evet_hayir_ile_degisir(): void
    {
        [$m] = $this->varyantlar();

        app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'aktif'],
            [$m->sku, 'Hayır'],
        ]));

        $this->assertFalse($m->fresh()->is_active);
    }

    public function test_csv_ile_stok_gelince_bekleyenlere_haber_verilir(): void
    {
        [$m] = $this->varyantlar();
        StockInquiry::create(['product_variant_id' => $m->id, 'email' => 'ayse@example.test']);

        app(UrunCsv::class)->iceAktar($this->csv([
            ['sku', 'stok'],
            [$m->sku, '3'],
        ]));

        Mail::assertSent(BackInStock::class);
    }

    public function test_sku_sutunu_olmayan_dosya_reddedilir(): void
    {
        $sonuc = app(UrunCsv::class)->iceAktar("urun;stok\nX;1");

        $this->assertStringContainsString('"sku"', $sonuc['hatalar'][0]);
    }

    public function test_panelden_yuklenir(): void
    {
        [$m] = $this->varyantlar();
        $yonetici = User::factory()->create(['role' => 'admin']);

        $dosya = UploadedFile::fake()->createWithContent('stok.csv', $this->csv([
            ['sku', 'stok'],
            [$m->sku, '8'],
        ]));

        Livewire::actingAs($yonetici)->test(ListProducts::class)
            ->callAction('csvIce', ['dosya' => $dosya])
            ->assertHasNoActionErrors()
            ->assertNotified('1 varyant güncellendi');

        $this->assertSame(8, $m->fresh()->stock);
    }

    public function test_panelden_indirilir(): void
    {
        $yonetici = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($yonetici)->test(ListProducts::class)
            ->callAction('csvDisa')
            ->assertFileDownloaded();
    }
}
