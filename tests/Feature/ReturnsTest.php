<?php

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\OrderShipping;
use App\Services\Returns;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReturnsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create(['name' => 'Kot Ceket', 'base_sku' => 'ZEYS-004']);

        app(VariantMatrix::class)->generate($this->product, [
            'Beden' => ['kind' => 'text', 'values' => ['M', 'L']],
        ], 2000.00);

        $this->product->variants->each->update(['stock' => 10]);
    }

    private function variant(string $label): ProductVariant
    {
        return $this->product->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $label);
    }

    /** Ödenmiş ve teslim edilmiş bir sipariş. */
    private function teslimEdilmisSiparis(int $adet = 2): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->variant('M'), $adet);

        $order = app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'Bağdat Cad. 1', 'district' => 'Kadıköy', 'city' => 'İstanbul'],
        );

        app(OrderPayments::class)->markPaid($order);

        $shipping = app(OrderShipping::class);
        $shipping->markShipped($order->fresh(), 'Yurtiçi Kargo', '1234567890');
        $shipping->markDelivered($order->fresh());

        return $order->fresh('items');
    }

    public function test_iade_onaylanince_stok_geri_gelir_ve_tutar_islenir(): void
    {
        $order = $this->teslimEdilmisSiparis(2);
        $variant = $this->variant('M');

        $this->assertSame(8, $variant->fresh()->stock);

        $returns = app(Returns::class);
        $talep = $returns->open($order, 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        $returns->markReceived($talep);
        $returns->approve($talep->fresh());

        $this->assertSame(9, $variant->fresh()->stock, 'Iade edilen parca stoga geri donmeli');

        $order->refresh();
        $this->assertSame('2000.00', $order->refunded_total);
        $this->assertSame('partially_refunded', $order->payment_status);
    }

    public function test_kismi_iadede_yalniz_iade_edilen_adet_geri_doner(): void
    {
        $order = $this->teslimEdilmisSiparis(3);
        $variant = $this->variant('M');

        $this->assertSame(7, $variant->fresh()->stock);

        $returns = app(Returns::class);
        $talep = $returns->open($order, 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        $returns->markReceived($talep);
        $returns->approve($talep->fresh());

        // Tum siparisin stogu degil, yalniz 1 adet
        $this->assertSame(8, $variant->fresh()->stock);
    }

    public function test_ayni_parca_iki_kez_iade_edilemez(): void
    {
        $order = $this->teslimEdilmisSiparis(2);
        $item = $order->items->first();
        $returns = app(Returns::class);

        $returns->open($order, 'return', 'beden', [$item->id => ['quantity' => 2]]);

        $this->assertSame(0, $returns->returnableQuantity($item->fresh()));

        $this->expectException(RuntimeException::class);
        $returns->open($order->fresh('items'), 'return', 'beden', [$item->id => ['quantity' => 1]]);
    }

    public function test_reddedilen_talep_iade_hakkini_geri_verir(): void
    {
        $order = $this->teslimEdilmisSiparis(2);
        $item = $order->items->first();
        $returns = app(Returns::class);

        $talep = $returns->open($order, 'return', 'beden', [$item->id => ['quantity' => 2]]);
        $returns->markReceived($talep);
        $returns->reject($talep->fresh(), 'Ürün kullanılmış olarak geldi.');

        // Reddedilen talep hakki tuketmemeli
        $this->assertSame(2, $returns->returnableQuantity($item->fresh()));
    }

    public function test_degisimde_para_geri_gitmez_yeni_varyant_rezerve_edilir(): void
    {
        $order = $this->teslimEdilmisSiparis(1);
        $m = $this->variant('M');
        $l = $this->variant('L');

        $returns = app(Returns::class);
        $talep = $returns->open($order, 'exchange', 'beden', [
            $order->items->first()->id => ['quantity' => 1, 'exchange_variant_id' => $l->id],
        ]);

        $returns->markReceived($talep);
        $returns->approve($talep->fresh());

        // M geri geldi
        $this->assertSame(10, $m->fresh()->stock);
        // L gonderilmek uzere rezerve edildi
        $this->assertSame(1, $l->fresh()->reserved);
        // Para iadesi YOK
        $this->assertSame('0.00', $order->fresh()->refunded_total);
        $this->assertSame('0.00', $talep->fresh()->refund_amount);
    }

    public function test_degisim_stogu_yoksa_talep_onaylanir_ama_not_dusulur(): void
    {
        $order = $this->teslimEdilmisSiparis(1);
        $l = $this->variant('L');
        $l->update(['stock' => 0]);

        $returns = app(Returns::class);
        $talep = $returns->open($order, 'exchange', 'beden', [
            $order->items->first()->id => ['quantity' => 1, 'exchange_variant_id' => $l->id],
        ]);

        $returns->markReceived($talep);
        $returns->approve($talep->fresh());

        $talep->refresh();
        // Musterinin urunu zaten depoda, geri ceviremeyiz
        $this->assertSame('approved', $talep->status);
        $this->assertStringContainsString('DEĞİŞİM STOĞU YETERSİZ', $talep->admin_note);
    }

    public function test_cayma_hakki_suresi_dolunca_talep_acilamaz(): void
    {
        $order = $this->teslimEdilmisSiparis(1);

        // 15 gun once teslim edilmis
        $order->forceFill(['delivered_at' => now()->subDays(15)])->save();

        $returns = app(Returns::class);
        $this->assertFalse($returns->isWithinWithdrawalPeriod($order->fresh()));

        $this->expectException(RuntimeException::class);
        $returns->open($order->fresh('items'), 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);
    }

    public function test_cayma_suresi_teslim_tarihinden_baslar(): void
    {
        $order = $this->teslimEdilmisSiparis(1);
        $returns = app(Returns::class);

        $deadline = $returns->withdrawalDeadline($order);
        $this->assertNotNull($deadline);
        $this->assertSame(14, (int) $order->delivered_at->diffInDays($deadline));

        // Henuz teslim edilmemis sipariste sure baslamaz
        $order->forceFill(['delivered_at' => null])->save();
        $this->assertNull($returns->withdrawalDeadline($order->fresh()));
        $this->assertTrue($returns->isWithinWithdrawalPeriod($order->fresh()));
    }

    public function test_odenmemis_siparis_icin_iade_acilamaz(): void
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->variant('M'), 1);

        $order = app(Checkout::class)->place(
            ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
            ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
        );

        $this->expectException(RuntimeException::class);
        app(Returns::class)->open($order, 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);
    }

    public function test_durum_makinesi_atlanamaz(): void
    {
        $order = $this->teslimEdilmisSiparis(1);
        $returns = app(Returns::class);

        $talep = $returns->open($order, 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        // Teslim alinmadan onaylanamaz
        $this->expectException(RuntimeException::class);
        $returns->approve($talep);
    }

    public function test_kargolanmamis_siparis_teslim_edilemez(): void
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->variant('M'), 1);

        $order = app(Checkout::class)->place(
            ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
            ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
        );

        app(OrderPayments::class)->markPaid($order);

        $this->expectException(RuntimeException::class);
        app(OrderShipping::class)->markDelivered($order->fresh());
    }

    public function test_yasal_metin_yeni_surumde_eskisi_silinmez(): void
    {
        $ilk = LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', 'Eski metin');

        $this->assertTrue($ilk->is_current);

        $yeni = LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', 'Yeni metin');

        $this->assertFalse($ilk->fresh()->is_current);
        $this->assertTrue($yeni->is_current);
        $this->assertNotSame($ilk->version, $yeni->version);

        // Eski surum OKUNABILIR kalmali — siparisler ona dayaniyor
        $this->assertSame('Eski metin', $ilk->fresh()->body);
        $this->assertSame('Yeni metin', LegalDocument::current('mesafeli-satis')->body);
    }
}
