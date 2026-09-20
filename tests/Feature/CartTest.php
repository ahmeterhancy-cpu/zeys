<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create(['name' => 'Oversize Gömlek', 'base_sku' => 'ZEYS-001']);

        app(VariantMatrix::class)->generate($this->product, [
            'Beden' => ['kind' => 'text', 'values' => ['S', 'M']],
            'Renk' => ['kind' => 'color', 'values' => [['value' => 'Siyah', 'color_hex' => '#111']]],
        ], 1000.00);

        $this->product->variants->each->update(['stock' => 10]);
    }

    private function cart(): Cart
    {
        return app(Cart::class);
    }

    private function variant(string $label): ProductVariant
    {
        return $this->product->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $label);
    }

    public function test_beden_ve_renk_ayri_satir(): void
    {
        $cart = $this->cart();
        $cart->add($this->variant('S / Siyah'), 1);
        $cart->add($this->variant('M / Siyah'), 1);

        $this->assertCount(2, $cart->lines());
        $this->assertSame(2, $cart->count());
    }

    public function test_ayni_varyant_tekrar_eklenince_adet_artar(): void
    {
        $cart = $this->cart();
        $variant = $this->variant('S / Siyah');

        $cart->add($variant, 2);
        $cart->add($variant, 3);

        $this->assertCount(1, $cart->lines());
        $this->assertSame(5, $cart->count());
    }

    public function test_fiyat_oturumdan_degil_veritabanindan_okunur(): void
    {
        $cart = $this->cart();
        $variant = $this->variant('S / Siyah');
        $cart->add($variant, 2);

        $this->assertSame(2000.00, $cart->subtotal());

        // Fiyat panelden degisti — sepet yeni fiyati gostermeli
        $variant->update(['price' => 1500.00]);

        $this->assertSame(3000.00, $cart->subtotal());
    }

    public function test_stok_azalinca_adet_kirpilir_ve_isaretlenir(): void
    {
        $cart = $this->cart();
        $variant = $this->variant('S / Siyah');
        $cart->add($variant, 8);

        $variant->update(['stock' => 3]);

        $line = $cart->lines()->first();
        $this->assertSame(3, $line['quantity']);
        $this->assertSame(8, $line['requested']);
        $this->assertTrue($line['adjusted'], 'Kirpilan satir isaretlenmeli');
    }

    public function test_pasif_urun_sepetten_dusur(): void
    {
        $cart = $this->cart();
        $cart->add($this->variant('S / Siyah'), 1);

        $this->product->update(['is_active' => false]);

        $this->assertTrue($cart->isEmpty());
    }

    public function test_ucretsiz_kargo_esigi(): void
    {
        config(['shop.kargo.ucret' => 99.00, 'shop.kargo.ucretsiz_esigi' => 1500.00]);

        $cart = $this->cart();
        $cart->add($this->variant('S / Siyah'), 1); // 1000 TL

        $this->assertSame(99.00, $cart->shipping());
        $this->assertSame(500.00, $cart->freeShippingRemaining());

        $cart->add($this->variant('M / Siyah'), 1); // 2000 TL

        $this->assertSame(0.00, $cart->shipping());
        $this->assertSame(0.00, $cart->freeShippingRemaining());
    }

    public function test_kupon_esigi_dusurunce_kargo_yeniden_ucretli_olur(): void
    {
        config(['shop.kargo.ucret' => 99.00, 'shop.kargo.ucretsiz_esigi' => 1500.00]);

        Coupon::create(['code' => 'ZEYS20', 'type' => 'percent', 'value' => 20]);

        $cart = $this->cart();
        $cart->add($this->variant('S / Siyah'), 1);
        $cart->add($this->variant('M / Siyah'), 1); // 2000 TL → kargo bedava

        $this->assertSame(0.00, $cart->shipping());

        $this->assertTrue($cart->applyCoupon('ZEYS20'));

        // 2000 - 400 = 1600 → hala esigin ustunde
        $this->assertSame(400.00, $cart->discount());
        $this->assertSame(0.00, $cart->shipping());
        $this->assertSame(1600.00, $cart->total());
    }

    public function test_kupon_indirimi_ara_toplami_asamaz(): void
    {
        Coupon::create(['code' => 'BEDAVA', 'type' => 'amount', 'value' => 99999]);

        $cart = $this->cart();
        $cart->add($this->variant('S / Siyah'), 1);
        $cart->applyCoupon('BEDAVA');

        $this->assertSame(1000.00, $cart->discount());
        $this->assertSame(0.00, round($cart->subtotal() - $cart->discount(), 2));
    }
}
