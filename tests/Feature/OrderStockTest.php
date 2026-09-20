<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderStock;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrderStockTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create(['name' => 'Keten Pantolon', 'base_sku' => 'ZEYS-002']);

        app(VariantMatrix::class)->generate($this->product, [
            'Beden' => ['kind' => 'text', 'values' => ['S', 'M']],
        ], 1200.00);
    }

    private function variant(string $label): ProductVariant
    {
        return $this->product->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $label);
    }

    private function siparisVer(ProductVariant $variant, int $adet): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($variant, $adet);

        return app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'Bağdat Cad. 1', 'district' => 'Kadıköy', 'city' => 'İstanbul'],
        );
    }

    public function test_rezervasyon_gercek_stoga_dokunmaz(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $this->siparisVer($variant, 2);

        $variant->refresh();
        $this->assertSame(5, $variant->stock, 'Odeme onaylanmadan gercek stok dusmemeli');
        $this->assertSame(2, $variant->reserved);
        $this->assertSame(3, $variant->available_stock);
    }

    public function test_odeme_onayinda_stok_duser_rezerv_biter(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $order = $this->siparisVer($variant, 2);
        app(OrderStock::class)->commit($order);

        $variant->refresh();
        $this->assertSame(3, $variant->stock);
        $this->assertSame(0, $variant->reserved);
        $this->assertSame('committed', $order->fresh()->stock_state);
    }

    public function test_ayni_callback_iki_kez_gelirse_stok_iki_kez_dusmez(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $order = $this->siparisVer($variant, 2);
        $stock = app(OrderStock::class);

        // PayTR callback'i tekrar gonderebiliyor
        $stock->commit($order);
        $stock->commit($order->fresh());
        $stock->commit($order->fresh());

        $variant->refresh();
        $this->assertSame(3, $variant->stock, 'Tekrarlanan callback stogu iki kez dusurmemeli');
        $this->assertSame(0, $variant->reserved);
    }

    public function test_odeme_dusunce_rezerv_serbest_kalir(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $order = $this->siparisVer($variant, 2);
        app(OrderStock::class)->release($order);

        $variant->refresh();
        $this->assertSame(5, $variant->stock);
        $this->assertSame(0, $variant->reserved);
        $this->assertSame(5, $variant->available_stock);
        $this->assertSame('none', $order->fresh()->stock_state);
    }

    public function test_iptal_edilen_siparisin_stogu_geri_doner(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $order = $this->siparisVer($variant, 2);
        $stock = app(OrderStock::class);

        $stock->commit($order);
        $stock->restore($order->fresh());

        $variant->refresh();
        $this->assertSame(5, $variant->stock);
        $this->assertSame(0, $variant->reserved);
    }

    public function test_iki_kez_geri_yukleme_hayalet_stok_yaratmaz(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $order = $this->siparisVer($variant, 2);
        $stock = app(OrderStock::class);

        $stock->commit($order);
        $stock->restore($order->fresh());
        $stock->restore($order->fresh());
        $stock->restore($order->fresh());

        $this->assertSame(5, $variant->fresh()->stock, 'Tekrarlanan iptal stogu sisirmemeli');
    }

    public function test_son_parca_iki_kez_satilamaz(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 1]);

        $this->siparisVer($variant, 1);

        // Ikinci musteri ayni son parcayi almaya calisiyor
        $this->expectException(RuntimeException::class);
        $this->siparisVer($variant, 1);
    }

    public function test_rezerv_negatife_dusmez(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $order = $this->siparisVer($variant, 2);

        // Tutarsiz veri: rezerv disaridan sifirlandi, sonra commit geliyor
        $variant->update(['reserved' => 0]);
        app(OrderStock::class)->commit($order);

        $this->assertSame(0, $variant->fresh()->reserved, 'Rezerv negatife dusmemeli');
    }

    public function test_reconcile_sismis_rezervi_duzeltir(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 10]);

        $this->siparisVer($variant, 2); // rezerv = 2

        // Bozulma: rezerv disaridan sisirildi
        $variant->update(['reserved' => 9]);
        $this->assertSame(1, $variant->fresh()->available_stock);

        app(OrderStock::class)->reconcile($variant->fresh());

        $variant->refresh();
        $this->assertSame(2, $variant->reserved, 'Acik siparislerin toplamina cekilmeli');
        $this->assertSame(8, $variant->available_stock);
    }

    public function test_stok_yetmezse_yarim_rezervasyon_kalmaz(): void
    {
        $s = $this->variant('S');
        $m = $this->variant('M');
        $s->update(['stock' => 5]);
        $m->update(['stock' => 1]);

        $cart = app(Cart::class);
        $cart->clear();
        $cart->add($s, 2);
        $cart->add($m, 1);

        // M'nin son parcasini araya giren baska bir siparis kapiyor
        $m->update(['reserved' => 1]);

        // Tukenen satir sepetten SESSIZCE silinmemeli
        $this->assertCount(2, $cart->lines());
        $this->assertTrue($cart->lines()->firstWhere('sold_out', true) !== null);

        $hata = null;

        try {
            app(Checkout::class)->place(
                ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
                ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
            );
        } catch (RuntimeException $e) {
            $hata = $e;
        }

        // Not: PHPUnit'in fail()'i de RuntimeException turevi, o yuzden
        // catch icinde degil disarida dogruluyoruz.
        $this->assertNotNull($hata, 'Stok yetersizken siparis gecmemeliydi');

        // S icin yarim rezervasyon birakilmamali
        $this->assertSame(0, $s->fresh()->reserved, 'Yarim rezervasyon hayalet stok demek');
        $this->assertSame(1, $m->fresh()->reserved, 'Araya giren siparisin rezervi calinmamali');
    }

    public function test_siparis_kalemi_varyant_kimligiyle_baglanir(): void
    {
        $variant = $this->variant('S');
        $variant->update(['stock' => 5]);

        $order = $this->siparisVer($variant, 1);
        $item = $order->items->first();

        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame($variant->sku, $item->sku);
        $this->assertSame('S', $item->variant_label);
        $this->assertSame('Keten Pantolon', $item->name);
    }
}
