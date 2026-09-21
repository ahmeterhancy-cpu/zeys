<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class VaryantGorselTest extends TestCase
{
    use RefreshDatabase;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->urun = Product::create(['name' => 'Saten Elbise', 'base_sku' => 'ZEYS-001', 'is_active' => true, 'hero_image' => 'urunler/kapak.jpg']);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['S', 'M']],
            'Renk' => ['kind' => 'color', 'values' => [
                ['value' => 'Siyah', 'color_hex' => '#111111'],
                ['value' => 'Bej', 'color_hex' => '#d8c9ae'],
            ]],
        ], 2890.00);

        $this->urun->variants()->update(['stock' => 5]);
    }

    private function varyant(string $etiket): ProductVariant
    {
        return $this->urun->fresh()->variants()->with('optionValues.option')->get()->firstWhere('label', $etiket);
    }

    private function panel()
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(VariantsRelationManager::class, [
                'ownerRecord' => $this->urun->fresh(),
                'pageClass' => EditProduct::class,
            ]);
    }

    public function test_panelde_varyanta_gorsel_yuklenir(): void
    {
        $v = $this->varyant('S / Siyah');

        $this->panel()
            ->callTableAction('edit', $v, [
                'image' => UploadedFile::fake()->image('siyah.jpg', 600, 700),
            ])
            ->assertHasNoTableActionErrors();

        $yol = $v->fresh()->image;
        $this->assertNotNull($yol);
        $this->assertStringStartsWith('urunler/varyant/', $yol);
        Storage::disk('public')->assertExists($yol);
    }

    public function test_secilen_varyantlara_toplu_gorsel_atanir_ve_kaldirilir(): void
    {
        $siyahlar = $this->urun->fresh()->variants()->with('optionValues.option')->get()
            ->filter(fn ($v) => str_contains($v->label, 'Siyah'));

        $this->panel()
            ->callTableBulkAction('gorselAta', $siyahlar, [
                'gorsel' => UploadedFile::fake()->image('siyah.jpg'),
            ])
            ->assertHasNoTableBulkActionErrors();

        $yollar = ProductVariant::whereIn('id', $siyahlar->pluck('id'))->pluck('image')->unique();
        $this->assertCount(1, $yollar, 'Aynı renkteki bedenler tek görseli paylaşmalı');
        $this->assertNotNull($yollar->first());
        $this->assertNull($this->varyant('S / Bej')->image, 'Seçilmeyen varyanta dokunulmamalı');

        $this->panel()->callTableBulkAction('gorselKaldir', $siyahlar);

        $this->assertSame(0, ProductVariant::whereNotNull('image')->count());
    }

    /**
     * GERİLEME: toplu eylem kapanışında `array $veri` yazılmıştı; Filament
     * parametreyi ADIYLA enjekte eder (`$data`), eylem her tıklamada hata
     * veriyordu. Bu eylem hiç test edilmemişti.
     */
    public function test_secilenlere_toplu_stok_atanir(): void
    {
        $this->panel()
            ->callTableBulkAction('stokAta', $this->urun->fresh()->variants, ['stok' => 12])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame([12], ProductVariant::pluck('stock')->unique()->values()->all());
    }

    public function test_urun_sayfasi_varyant_gorselini_betige_verir(): void
    {
        $this->varyant('M / Bej')->update(['image' => 'urunler/varyant/bej.jpg']);

        $html = $this->get('/urun/'.$this->urun->slug)->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" id="varyant-verisi">(.+?)</script>#s', $html, $m));
        $veri = collect(json_decode($m[1], true));

        $this->assertSame('urunler/varyant/bej.jpg', $veri->firstWhere('sku', $this->varyant('M / Bej')->sku)['gorsel']);
        $this->assertNull($veri->firstWhere('sku', $this->varyant('S / Siyah')->sku)['gorsel']);
    }

    public function test_hic_gorsel_yoksa_acilis_gorseli_varyanttan_gelir(): void
    {
        $this->urun->update(['hero_image' => null]);
        $this->varyant('S / Siyah')->update(['image' => 'urunler/varyant/siyah.jpg']);

        $this->get('/urun/'.$this->urun->slug)
            ->assertOk()
            ->assertSee('id="galeri-ana"', false)
            ->assertSee('urunler/varyant/siyah.jpg', false);
    }

    public function test_sepet_ve_siparis_varyant_gorselini_kullanir(): void
    {
        $bej = $this->varyant('M / Bej');
        $bej->update(['image' => 'urunler/varyant/bej.jpg']);
        $siyah = $this->varyant('S / Siyah');

        app(Cart::class)->add($bej, 1);
        app(Cart::class)->add($siyah, 1);

        $this->get('/sepet')->assertOk()
            ->assertSee('storage/urunler/varyant/bej.jpg', false)
            ->assertSee('storage/urunler/kapak.jpg', false);

        $order = app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'Cumhuriyet Mah. 1', 'district' => 'Merkez', 'city' => 'Edirne'],
        );

        $this->assertSame('urunler/varyant/bej.jpg', $order->items->firstWhere('product_variant_id', $bej->id)->image);
        $this->assertSame('urunler/kapak.jpg', $order->items->firstWhere('product_variant_id', $siyah->id)->image, 'Görselsiz varyantta kapak');
    }
}
