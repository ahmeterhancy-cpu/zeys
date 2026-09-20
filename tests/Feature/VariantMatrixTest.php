<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariantMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function eksenler(): array
    {
        return [
            'Beden' => ['kind' => 'text', 'values' => ['S', 'M', 'L']],
            'Renk' => ['kind' => 'color', 'values' => [
                ['value' => 'Siyah', 'color_hex' => '#111111'],
                ['value' => 'Bej', 'color_hex' => '#d8cbb4'],
            ]],
        ];
    }

    private function urun(): Product
    {
        return Product::create(['name' => 'Oversize Gömlek', 'base_sku' => 'ZEYS-001']);
    }

    public function test_tum_kombinasyonlar_uretilir(): void
    {
        $product = $this->urun();

        app(VariantMatrix::class)->generate($product, $this->eksenler(), 1299.00);

        // 3 beden × 2 renk
        $this->assertSame(6, $product->variants()->count());

        $etiketler = $product->variants()->with('optionValues.option')->get()
            ->map(fn (ProductVariant $v) => $v->label)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['L / Bej', 'L / Siyah', 'M / Bej', 'M / Siyah', 'S / Bej', 'S / Siyah'],
            $etiketler
        );
    }

    public function test_her_varyantin_skusu_benzersiz(): void
    {
        $product = $this->urun();

        app(VariantMatrix::class)->generate($product, $this->eksenler());

        $skular = $product->variants()->pluck('sku');

        $this->assertCount(6, $skular);
        $this->assertCount(6, $skular->unique());
        $this->assertTrue($skular->every(fn ($s) => str_starts_with($s, 'ZEYS-001-')));
    }

    public function test_yeniden_uretimde_mevcut_fiyat_ve_stok_korunur(): void
    {
        $product = $this->urun();
        $matrix = app(VariantMatrix::class);

        $matrix->generate($product, $this->eksenler(), 1299.00);

        $variant = $product->variants()->first();
        $variant->update(['price' => 1599.00, 'stock' => 12]);

        // Yeni bir renk eklenip matris yeniden üretiliyor
        $eksenler = $this->eksenler();
        $eksenler['Renk']['values'][] = ['value' => 'Lacivert', 'color_hex' => '#1b2a4a'];

        $matrix->generate($product->fresh(), $eksenler, 1299.00);

        $this->assertSame(9, $product->variants()->count());

        $variant->refresh();
        $this->assertSame('1599.00', $variant->price);
        $this->assertSame(12, $variant->stock);
    }

    public function test_onbellek_satilabilir_stoktan_hesaplanir(): void
    {
        $product = $this->urun();
        $matrix = app(VariantMatrix::class);

        $matrix->generate($product, $this->eksenler(), 1299.00);

        $variants = $product->variants()->orderBy('id')->get();
        $variants[0]->update(['price' => 999.00, 'stock' => 5, 'reserved' => 2]);
        $variants[1]->update(['price' => 1899.00, 'stock' => 3, 'reserved' => 0]);

        $matrix->refreshProduct($product->fresh());
        $product->refresh();

        $this->assertSame('999.00', $product->min_price);
        $this->assertSame('1899.00', $product->max_price);
        // (5-2) + 3 + sifir stoklu 4 varyant
        $this->assertSame(6, $product->total_stock);
    }

    public function test_varyant_degisince_onbellek_kendiliginden_tazelenir(): void
    {
        $product = $this->urun();
        app(VariantMatrix::class)->generate($product, $this->eksenler(), 1299.00);

        $this->assertSame(0, $product->fresh()->total_stock);

        // refreshProduct ELLE cagrilmiyor — gozlemci halletmeli
        $product->variants()->first()->update(['stock' => 7, 'price' => 899.00]);

        $product->refresh();
        $this->assertSame(7, $product->total_stock);
        $this->assertSame('899.00', $product->min_price);
    }

    public function test_stokta_olmayan_kombinasyon_secilebilir_degil(): void
    {
        $product = $this->urun();
        $matrix = app(VariantMatrix::class);

        $matrix->generate($product, $this->eksenler(), 1299.00);

        $siyah = ProductOptionValue::where('value', 'Siyah')->firstOrFail();
        $m = ProductOptionValue::where('value', 'M')->firstOrFail();
        $l = ProductOptionValue::where('value', 'L')->firstOrFail();

        // Sadece "Siyah L" stokta
        $siyahL = $matrix->findVariant($product, [$siyah->id, $l->id]);
        $siyahL->update(['stock' => 4]);

        $secilebilir = $matrix->availableValueIds($product->fresh(), [$siyah->id]);

        $this->assertContains($l->id, $secilebilir, 'Siyah L stokta, L secilebilir olmali');
        $this->assertNotContains($m->id, $secilebilir, 'Siyah M bitmis, M sonuk olmali');
    }

    public function test_eksik_kombinasyon_varyant_dondurmez(): void
    {
        $product = $this->urun();
        $matrix = app(VariantMatrix::class);

        $matrix->generate($product, $this->eksenler(), 1299.00);

        $siyah = ProductOptionValue::where('value', 'Siyah')->firstOrFail();
        $m = ProductOptionValue::where('value', 'M')->firstOrFail();

        // Yalniz renk verildi, beden eksik — sepete yanlis SKU girmemeli
        $this->assertNull($matrix->findVariant($product, [$siyah->id]));

        // Tam kombinasyon eslesmeli
        $tam = $matrix->findVariant($product, [$siyah->id, $m->id]);
        $this->assertNotNull($tam);
        $this->assertSame('M / Siyah', $tam->load('optionValues.option')->label);
    }

    public function test_rezerve_edilen_adet_satilabilir_stoktan_dusulur(): void
    {
        $product = $this->urun();
        app(VariantMatrix::class)->generate($product, $this->eksenler(), 1299.00);

        $variant = $product->variants()->first();
        $variant->update(['stock' => 3, 'reserved' => 3]);

        $this->assertSame(0, $variant->fresh()->available_stock);
        $this->assertFalse($variant->fresh()->is_orderable);
    }

    public function test_satilabilir_stok_asla_negatife_dusmez(): void
    {
        $product = $this->urun();
        app(VariantMatrix::class)->generate($product, $this->eksenler(), 1299.00);

        // Tutarsiz veri: rezerve, stoktan buyuk
        $variant = $product->variants()->first();
        $variant->update(['stock' => 2, 'reserved' => 5]);

        $this->assertSame(0, $variant->fresh()->available_stock);
    }
}
