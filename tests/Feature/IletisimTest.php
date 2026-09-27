<?php

namespace Tests\Feature;

use App\Support\Iletisim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İletişim sayfası, harita gömmesi ve sabit WhatsApp düğmesi.
 */
class IletisimTest extends TestCase
{
    use RefreshDatabase;

    public function test_yerel_numara_uluslararasi_bicime_cevrilir(): void
    {
        config(['shop.iletisim.whatsapp' => '05551800206']);
        $this->assertSame('905551800206', Iletisim::whatsappNumarasi());
        $this->assertSame('0555 180 02 06', Iletisim::whatsappGosterim());

        // Boşluklu / uluslararası yazımlar da aynı sonucu vermeli
        config(['shop.iletisim.whatsapp' => '+90 555 180 02 06']);
        $this->assertSame('905551800206', Iletisim::whatsappNumarasi());

        config(['shop.iletisim.whatsapp' => '555 180 02 06']);
        $this->assertSame('905551800206', Iletisim::whatsappNumarasi());
    }

    public function test_numara_yoksa_dugme_cizilmez(): void
    {
        config(['shop.iletisim.whatsapp' => '']);

        $this->assertNull(Iletisim::whatsappBaglanti());
        $this->get('/')->assertOk()->assertDontSee('wa-dugme', false);
    }

    public function test_whatsapp_dugmesi_her_sayfada_sabit_durur(): void
    {
        config(['shop.iletisim.whatsapp' => '05551800206']);

        foreach (['/', '/iletisim', '/sepet'] as $yol) {
            $this->get($yol)
                ->assertOk()
                ->assertSee('class="wa-dugme"', false)
                ->assertSee('https://wa.me/905551800206', false);
        }
    }

    public function test_iletisim_sayfasi_harita_ve_yol_tarifi_gosterir(): void
    {
        config([
            'shop.satici.adres' => 'Cumhuriyet Mahallesi 3049. Sokak No:3, Edirne',
            'shop.iletisim.harita_embed' => '',
            'shop.iletisim.harita_sorgu' => 'Zeys Fashion House Edirne',
        ]);

        $this->get('/iletisim')
            ->assertOk()
            ->assertSee('<iframe', false)
            ->assertSee('google.com/maps?q=Zeys%20Fashion%20House%20Edirne', false)
            ->assertSee('Yol tarifi al')
            ->assertSee('loading="lazy"', false);
    }

    public function test_sorgu_bossa_adrese_duser(): void
    {
        config([
            'shop.iletisim.harita_embed' => '',
            'shop.iletisim.harita_sorgu' => '',
            'shop.satici.adres' => 'Saraclar Caddesi 1, Edirne',
        ]);

        $this->assertStringContainsString('Saraclar', (string) Iletisim::haritaGomme());
    }

    public function test_harita_ayardan_verilirse_o_kullanilir(): void
    {
        config(['shop.iletisim.harita_embed' => 'https://www.google.com/maps/embed?pb=ELLE-GIRILEN']);

        $this->get('/iletisim')
            ->assertOk()
            ->assertSee('ELLE-GIRILEN', false);
    }

    public function test_iletisim_sayfasi_kanallari_ve_yonlendirmeleri_listeler(): void
    {
        config([
            'shop.satici.telefon' => '0284 000 00 00',
            'shop.satici.eposta' => 'info@zeysfashionhouse.com',
            'shop.iletisim.calisma_saatleri' => 'Pazartesi - Cumartesi 10.00 - 20.00',
        ]);

        $this->get('/iletisim')
            ->assertOk()
            ->assertSee('0284 000 00 00')
            ->assertSee('info@zeysfashionhouse.com')
            ->assertSee('Pazartesi - Cumartesi 10.00 - 20.00')
            ->assertSee('Siparişimi sorgula')
            ->assertSee('İade ve değişim');
    }
}
