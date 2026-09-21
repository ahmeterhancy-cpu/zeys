<?php

namespace Tests\Feature;

use App\Mail\OrderPlaced;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Rules\TcKimlikNo;
use App\Services\Cart;
use App\Services\OrderPayments;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FaturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', 'Metin');

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
            'fatura_tipi' => 'bireysel',
        ], $ek);
    }

    public function test_tckn_saglama_algoritmasi(): void
    {
        $this->assertTrue(TcKimlikNo::gecerliMi('10000000146'));
        $this->assertTrue(TcKimlikNo::gecerliMi('11111111110'));

        $this->assertFalse(TcKimlikNo::gecerliMi('10000000145'), 'Son hane yanlis');
        $this->assertFalse(TcKimlikNo::gecerliMi('10000000156'), '10. hane yanlis');
        $this->assertFalse(TcKimlikNo::gecerliMi('01234567890'), 'Ilk hane 0 olamaz');
        $this->assertFalse(TcKimlikNo::gecerliMi('1234567890'), '10 hane');
        $this->assertFalse(TcKimlikNo::gecerliMi('1000000014a'), 'Harf');
    }

    public function test_bireysel_tckn_istege_bagli(): void
    {
        $this->post('/odeme', $this->form())->assertSessionHasNoErrors();

        $fatura = Order::firstOrFail()->billing_address;

        $this->assertSame('individual', $fatura['invoice_type']);
        $this->assertNull($fatura['tax_number']);
        // Farkli adres verilmediyse teslimat adresi kopyalanir
        $this->assertSame('Edirne', $fatura['city']);
    }

    public function test_gecersiz_tckn_reddedilir(): void
    {
        $this->post('/odeme', $this->form(['tckn' => '12345678901']))
            ->assertSessionHasErrors('tckn');

        $this->assertSame(0, Order::count());
    }

    public function test_gecerli_tckn_kaydedilir(): void
    {
        $this->post('/odeme', $this->form(['tckn' => '10000000146']))->assertSessionHasNoErrors();

        $this->assertSame('10000000146', Order::firstOrFail()->billing_address['tax_number']);
    }

    public function test_kurumsal_fatura_alanlari_zorunlu(): void
    {
        $this->post('/odeme', $this->form(['fatura_tipi' => 'kurumsal']))
            ->assertSessionHasErrors(['firma_unvani', 'vergi_dairesi', 'vkn']);
    }

    public function test_vkn_on_haneli_olmali(): void
    {
        $this->post('/odeme', $this->form([
            'fatura_tipi' => 'kurumsal',
            'firma_unvani' => 'Örnek Tekstil A.Ş.',
            'vergi_dairesi' => 'Edirne',
            'vkn' => '12345',
        ]))->assertSessionHasErrors('vkn');
    }

    public function test_kurumsal_fatura_kaydedilir(): void
    {
        $this->post('/odeme', $this->form([
            'fatura_tipi' => 'kurumsal',
            'firma_unvani' => 'Örnek Tekstil A.Ş.',
            'vergi_dairesi' => 'Edirne',
            'vkn' => '1234567890',
            'tckn' => '10000000146', // kurumsalda yok sayilmali
        ]))->assertSessionHasNoErrors();

        $fatura = Order::firstOrFail()->billing_address;

        $this->assertSame('corporate', $fatura['invoice_type']);
        $this->assertSame('Örnek Tekstil A.Ş.', $fatura['company_name']);
        $this->assertSame('1234567890', $fatura['tax_number']);
    }

    public function test_farkli_fatura_adresi(): void
    {
        $this->post('/odeme', $this->form([
            'farkli_fatura_adresi' => '1',
            'fatura_adres' => 'Saraçlar Cad. 7',
            'fatura_ilce' => 'Merkez',
            'fatura_il' => 'Kırklareli',
        ]))->assertSessionHasNoErrors();

        $order = Order::firstOrFail();

        $this->assertSame('Kırklareli', $order->billing_address['city']);
        $this->assertSame('Edirne', $order->shipping_address['city'], 'Teslimat adresi degismemeli');
    }

    public function test_farkli_adres_isaretlenip_bos_birakilamaz(): void
    {
        $this->post('/odeme', $this->form(['farkli_fatura_adresi' => '1']))
            ->assertSessionHasErrors(['fatura_adres', 'fatura_il']);
    }

    public function test_epostada_tckn_maskelenir(): void
    {
        $this->post('/odeme', $this->form(['tckn' => '10000000146']));

        app(OrderPayments::class)->markPaid(Order::firstOrFail());

        Mail::assertSent(OrderPlaced::class, function (OrderPlaced $mail) {
            $govde = $mail->render();

            // E-posta guvenli kanal degil — tam numara gecmemeli
            return ! str_contains($govde, '10000000146')
                && str_contains($govde, '0146');
        });
    }

    public function test_panelde_fatura_tam_gorunur(): void
    {
        $this->post('/odeme', $this->form(['tckn' => '10000000146']));

        $yonetici = User::factory()->create(['role' => 'admin']);

        $this->actingAs($yonetici)
            ->get('/admin/orders/'.Order::firstOrFail()->id.'/edit')
            ->assertOk()
            ->assertSee('10000000146');
    }
}
