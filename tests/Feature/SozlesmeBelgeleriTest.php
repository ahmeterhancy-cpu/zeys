<?php

namespace Tests\Feature;

use App\Mail\SozlesmeBelgeleri;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Services\Cart;
use App\Services\OrderPayments;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SozlesmeBelgeleriTest extends TestCase
{
    use RefreshDatabase;

    private LegalDocument $onBilgi;

    private LegalDocument $sozlesme;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->onBilgi = LegalDocument::publish('on-bilgilendirme', 'Ön Bilgilendirme Formu', '<p>ON BILGI METNI v1</p>');
        $this->sozlesme = LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', '<p>SOZLESME METNI v1</p>');

        $urun = Product::create(['name' => 'Saten Midi Elbise', 'is_active' => true]);
        app(VariantMatrix::class)->generate($urun, ['Beden' => ['kind' => 'text', 'values' => ['M']]], 2890.00);
        $urun->fresh()->variants->each->update(['stock' => 5]);

        app(Cart::class)->add($urun->fresh()->variants->first(), 1);
    }

    private function form(array $ek = []): array
    {
        return array_merge([
            'ad' => 'Ayşe Yılmaz',
            'eposta' => 'ayse@example.test',
            'telefon' => '5551112233',
            'adres' => 'Cumhuriyet Mah. 1',
            'ilce' => 'Merkez',
            'il' => 'Edirne',
            'sozlesme_onay' => '1',
            'sozlesme_surum' => $this->sozlesme->version,
            'onbilgi_surum' => $this->onBilgi->version,
        ], $ek);
    }

    public function test_iki_metnin_surumu_de_siparise_kaydedilir(): void
    {
        $this->post('/odeme', $this->form())->assertSessionHasNoErrors();

        $order = Order::firstOrFail();

        $this->assertSame($this->sozlesme->version, $order->contract_version);
        $this->assertSame($this->onBilgi->version, $order->preinfo_version);
    }

    public function test_kasa_acikken_surum_degisirse_siparis_alinmaz(): void
    {
        // Musteri kasayi v1 ile acti; o sirada yonetici v2 yayimladi
        $gorulen = $this->form();
        LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', '<p>SOZLESME METNI v2</p>');

        $this->post('/odeme', $gorulen)->assertSessionHas('hata');

        // Sunucu sessizce v2'yi kaydetseydi kanit yanlis olurdu
        $this->assertSame(0, Order::count());
    }

    public function test_odeme_onaylaninca_sozlesme_epostasi_gider(): void
    {
        $this->post('/odeme', $this->form());
        $order = Order::firstOrFail();

        Mail::assertNotSent(SozlesmeBelgeleri::class); // odeme oncesi degil

        app(OrderPayments::class)->markPaid($order);

        Mail::assertSent(SozlesmeBelgeleri::class, function (SozlesmeBelgeleri $mail) {
            $govde = $mail->render();

            return $mail->hasTo('ayse@example.test')
                && str_contains($govde, 'ON BILGI METNI v1')
                && str_contains($govde, 'SOZLESME METNI v1')
                && str_contains($govde, 'Saten Midi Elbise');
        });

        $this->assertNotNull($order->fresh()->contract_sent_at, 'Gonderim ani kanit olarak kaydedilmeli');
    }

    public function test_sonradan_yayimlanan_surum_degil_onaylanan_gonderilir(): void
    {
        $this->post('/odeme', $this->form());
        $order = Order::firstOrFail();

        // Siparis sonrasi metin guncellendi
        LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', '<p>SOZLESME METNI v2</p>');

        app(OrderPayments::class)->markPaid($order);

        Mail::assertSent(SozlesmeBelgeleri::class, function (SozlesmeBelgeleri $mail) {
            $govde = $mail->render();

            return str_contains($govde, 'SOZLESME METNI v1')
                && ! str_contains($govde, 'SOZLESME METNI v2');
        });
    }

    public function test_sozlesme_magazaya_kopyalanmaz(): void
    {
        config(['shop.siparis_bildirim_epostasi' => 'magaza@example.test']);

        $this->post('/odeme', $this->form());
        app(OrderPayments::class)->markPaid(Order::firstOrFail());

        Mail::assertSent(SozlesmeBelgeleri::class, 1);
        Mail::assertNotSent(SozlesmeBelgeleri::class, fn ($m) => $m->hasTo('magaza@example.test'));
    }
}
