<?php

namespace Tests\Feature;

use App\Filament\Resources\EpostaSablonlari\Pages\EditEpostaSablonu;
use App\Filament\Resources\EpostaSablonlari\Pages\ListEpostaSablonlari;
use App\Mail\OrderPlaced;
use App\Mail\OrderShipped;
use App\Models\EpostaSablonu;
use App\Models\Order;
use App\Models\User;
use App\Support\EpostaMetni;
use App\Support\EpostaOnizleme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EpostaMetniTest extends TestCase
{
    use RefreshDatabase;

    private function siparis(): Order
    {
        return Order::create([
            'number' => 'ZEY-260921-0007',
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.test',
            'customer_phone' => '5551112233',
            'shipping_address' => ['city' => 'Edirne'],
            'grand_total' => 2890,
            'shipping_carrier' => 'Aras',
            'tracking_number' => 'TR777',
        ]);
    }

    public function test_kayit_yokken_varsayilan_metin(): void
    {
        $mail = new OrderPlaced($this->siparis());

        $this->assertSame('Siparişiniz alındı — ZEY-260921-0007', $mail->envelope()->subject);
        $mail->assertSeeInHtml('Merhaba Ayşe Yılmaz, siparişinizi aldık');
    }

    public function test_panel_metni_varsayilani_ezer_ve_degiskenler_dolar(): void
    {
        EpostaSablonu::create([
            'anahtar' => 'siparis-kargoda',
            'konu' => '{ad}, kargon yolda! ({siparis_no})',
            'metin' => "Merhaba {ad},\n{kargo_firma} ile gönderdik: {takip_no}",
        ]);

        $mail = new OrderShipped($this->siparis());

        $this->assertSame('Ayşe Yılmaz, kargon yolda! (ZEY-260921-0007)', $mail->envelope()->subject);
        $mail->assertSeeInHtml('Aras ile gönderdik: TR777', false);
        // Boş bırakılan başlık varsayılana döner
        $mail->assertSeeInHtml('Siparişiniz yola çıktı');
    }

    public function test_tire_notu_gizler_ve_html_kacirilir(): void
    {
        EpostaSablonu::create(['anahtar' => 'siparis-alindi', 'not' => '-', 'metin' => '<script>x</script> Merhaba']);

        $metin = EpostaMetni::al('siparis-alindi', ['ad' => 'A']);
        $this->assertSame('', $metin['not']);

        $mail = new OrderPlaced($this->siparis());
        $mail->assertDontSeeInHtml('iade ya da değişim hakkınız var');
        $mail->assertDontSeeInHtml('<script>x</script>', false);
    }

    public function test_bilinmeyen_degisken_oldugu_gibi_kalir(): void
    {
        EpostaSablonu::create(['anahtar' => 'siparis-alindi', 'baslik' => 'Sevgili {adi}']);

        $this->assertSame('Sevgili {adi}', EpostaMetni::al('siparis-alindi', ['ad' => 'A'])['baslik']);
    }

    public function test_her_e_posta_ornek_veriyle_onizlenir(): void
    {
        // Mağaza boşken (sipariş/ürün yok) bile önizleme çizilmeli
        foreach (array_keys(EpostaMetni::SABLONLAR) as $anahtar) {
            $this->assertStringContainsString(
                e(EpostaMetni::SABLONLAR[$anahtar]['baslik']),
                EpostaOnizleme::html($anahtar),
                $anahtar.' önizlemesi başlığı içermeli',
            );
        }
    }

    public function test_panelde_liste_duzenleme_ve_varsayilana_donus(): void
    {
        $yonetici = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($yonetici)->test(ListEpostaSablonlari::class)
            ->assertCanSeeTableRecords(EpostaSablonu::all())
            ->assertSee('Sipariş kargoya verildi');

        $this->assertSame(count(EpostaMetni::SABLONLAR), EpostaSablonu::count(), 'Liste her e-postayı açmalı');

        $kayit = EpostaSablonu::where('anahtar', 'stokta')->first();

        Livewire::actingAs($yonetici)->test(EditEpostaSablonu::class, ['record' => $kayit->id])
            ->fillForm(['baslik' => 'Müjde! Beklediğin ürün geldi'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Müjde! Beklediğin ürün geldi', EpostaMetni::al('stokta')['baslik']);

        Livewire::actingAs($yonetici)->test(EditEpostaSablonu::class, ['record' => $kayit->id])
            ->callAction('varsayilan');

        $this->assertSame('Beklediğiniz ürün stokta', EpostaMetni::al('stokta')['baslik']);
    }

    public function test_konu_gizlenemez(): void
    {
        $kayit = EpostaSablonu::create(['anahtar' => 'stokta']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(EditEpostaSablonu::class, ['record' => $kayit->id])
            ->fillForm(['konu' => '-'])
            ->call('save')
            ->assertHasFormErrors(['konu']);
    }

    public function test_personel_giremez(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/admin/eposta-metinleri')->assertForbidden();
    }
}
