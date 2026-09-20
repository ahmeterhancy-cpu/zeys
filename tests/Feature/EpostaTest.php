<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Mail\OrderShipped;
use App\Mail\ReturnResolved;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\Notifier;
use App\Services\OrderPayments;
use App\Services\OrderShipping;
use App\Services\Returns;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EpostaTest extends TestCase
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

        $this->urun->fresh()->variants->each->update(['stock' => 5]);
    }

    private function varyant(string $etiket): ProductVariant
    {
        return $this->urun->fresh()->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $etiket);
    }

    private function siparisVer(int $adet = 1): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), $adet);

        return app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'Bağdat Cad. 1', 'district' => 'Kadıköy', 'city' => 'İstanbul'],
        );
    }

    public function test_siparis_verilince_degil_odeme_onaylanınca_eposta_gider(): void
    {
        $order = $this->siparisVer();

        // Odeme henuz onaylanmadi — posta GITMEMELI
        Mail::assertNothingSent();

        app(OrderPayments::class)->markPaid($order);

        Mail::assertSent(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->hasTo('ayse@example.test')
            && $mail->order->is($order));
    }

    public function test_odeme_dusunce_onay_epostasi_gitmez(): void
    {
        $order = $this->siparisVer();

        app(OrderPayments::class)->markFailed($order, 'Yetersiz bakiye');

        Mail::assertNotSent(OrderPlaced::class);
    }

    public function test_tekrarlanan_callback_iki_kez_eposta_gondermez(): void
    {
        $order = $this->siparisVer();
        $payments = app(OrderPayments::class);

        $payments->markPaid($order);
        $payments->markPaid($order->fresh());
        $payments->markPaid($order->fresh());

        Mail::assertSent(OrderPlaced::class, 1);
    }

    public function test_kargoya_verilince_takip_numarali_eposta_gider(): void
    {
        $order = $this->siparisVer();
        app(OrderPayments::class)->markPaid($order);

        app(OrderShipping::class)->markShipped($order->fresh(), 'Yurtiçi Kargo', '1234567890');

        Mail::assertSent(OrderShipped::class, function (OrderShipped $mail) {
            return $mail->hasTo('ayse@example.test')
                && $mail->order->tracking_number === '1234567890';
        });
    }

    public function test_iade_onayinda_eposta_gider(): void
    {
        $order = $this->siparisVer();
        app(OrderPayments::class)->markPaid($order);

        $shipping = app(OrderShipping::class);
        $shipping->markShipped($order->fresh(), 'Yurtiçi', '1');
        $shipping->markDelivered($order->fresh());

        $returns = app(Returns::class);
        $talep = $returns->open($order->fresh('items'), 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        $returns->markReceived($talep);
        $returns->approve($talep->fresh());

        Mail::assertSent(ReturnResolved::class, fn (ReturnResolved $mail) => $mail->talep->status === 'approved');
    }

    public function test_iade_reddinde_gerekce_epostaya_girer(): void
    {
        $order = $this->siparisVer();
        app(OrderPayments::class)->markPaid($order);

        $shipping = app(OrderShipping::class);
        $shipping->markShipped($order->fresh(), 'Yurtiçi', '1');
        $shipping->markDelivered($order->fresh());

        $returns = app(Returns::class);
        $talep = $returns->open($order->fresh('items'), 'return', 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);

        $returns->markReceived($talep);
        $returns->reject($talep->fresh(), 'Ürün kullanılmış olarak geldi.');

        Mail::assertSent(ReturnResolved::class, function (ReturnResolved $mail) {
            return $mail->talep->status === 'rejected'
                && str_contains($mail->talep->admin_note, 'kullanılmış');
        });
    }

    public function test_gonderim_hatasi_siparisi_bozmaz(): void
    {
        /*
         * KRITIK: PayTR callback'i gövdesi "OK" olmayan yaniti basarisiz
         * sayip bildirimi tekrar gonderiyor. SMTP coktugu icin siparisin
         * islenmemesi ya da sonsuz tekrar kabul edilemez.
         */
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP baglanti hatasi'));

        $order = $this->siparisVer();

        app(OrderPayments::class)->markPaid($order);

        // Posta gitmedi ama siparis DOGRU islendi
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('committed', $order->stock_state);
    }

    public function test_gecersiz_eposta_adresi_sessizce_atlanir(): void
    {
        $order = $this->siparisVer();
        $order->forceFill(['customer_email' => 'bozuk-adres'])->save();

        $sonuc = app(Notifier::class)->orderPlaced($order->fresh());

        $this->assertFalse($sonuc);
        Mail::assertNothingSent();
    }

    public function test_onay_epostasi_siparis_ozetini_icerir(): void
    {
        $order = $this->siparisVer(2);
        app(OrderPayments::class)->markPaid($order);

        Mail::assertSent(OrderPlaced::class, function (OrderPlaced $mail) use ($order) {
            $govde = $mail->render();

            return str_contains($govde, $order->number)
                && str_contains($govde, 'Saten Midi Elbise')
                && str_contains($govde, '5.780,00 TL')
                && str_contains($govde, 'Kadıköy');
        });
    }

    public function test_kargo_epostasi_takip_numarasini_gosterir(): void
    {
        $order = $this->siparisVer();
        app(OrderPayments::class)->markPaid($order);
        app(OrderShipping::class)->markShipped($order->fresh(), 'Yurtiçi Kargo', '9876543210');

        Mail::assertSent(OrderShipped::class, function (OrderShipped $mail) {
            $govde = $mail->render();

            return str_contains($govde, '9876543210')
                && str_contains($govde, 'Yurtiçi Kargo');
        });
    }
}
