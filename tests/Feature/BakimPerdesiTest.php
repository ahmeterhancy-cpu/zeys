<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteAyarlari;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BakimPerdesiTest extends TestCase
{
    use RefreshDatabase;

    public function test_perde_kapaliyken_site_acik(): void
    {
        config(['shop.bakim_modu' => false]);

        $this->get('/')->assertOk()->assertDontSee('Çok yakında', false);
    }

    public function test_perde_acikken_ziyaretci_503_gorur(): void
    {
        config(['shop.bakim_modu' => true]);

        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '3600')
            ->assertSee('Çok yakında', false);

        $this->get('/koleksiyonlar')->assertStatus(503);
        $this->get('/sepet')->assertStatus(503);
    }

    public function test_perde_acikken_yonetici_siteyi_normal_gorur_ve_uyarilir(): void
    {
        config(['shop.bakim_modu' => true]);

        $yonetici = User::factory()->create(['role' => 'admin']);

        $this->actingAs($yonetici)->get('/')
            ->assertOk()
            ->assertSee('Bakım perdesi AÇIK', false);
    }

    public function test_perde_acikken_musteri_hesabi_da_perdeyi_gorur(): void
    {
        config(['shop.bakim_modu' => true]);

        $musteri = User::factory()->create(['role' => 'customer']);

        $this->actingAs($musteri)->get('/')->assertStatus(503);
    }

    public function test_perde_acikken_panel_girisi_calisir(): void
    {
        config(['shop.bakim_modu' => true]);

        $this->get('/admin/login')->assertOk();
    }

    public function test_perde_acikken_livewire_rastgele_onekli_yol_gecer(): void
    {
        /*
         * GERILEME (referans proje): Filament Livewire'i /livewire-172643c6/update
         * gibi rastgele onekle servis ediyor. Gecis listesinde "livewire/*"
         * olsaydi bu yol eslesmez, perde acikken panele giris yapilamazdi.
         */
        config(['shop.bakim_modu' => true]);

        $yanit = $this->post('/livewire-172643c6/update');

        $this->assertNotSame(503, $yanit->status(), 'Livewire istegi perdeye takilmamali');
    }

    public function test_perde_acikken_paytr_bildirimi_islenir(): void
    {
        config([
            'shop.bakim_modu' => true,
            'paytr.merchant_key' => 'k',
            'paytr.merchant_salt' => 's',
        ]);

        // Bilinmeyen siparis — yine de perde yerine PayTR ucu yanit vermeli
        $oid = 'X1';
        $this->post('/paytr/callback', [
            'merchant_oid' => $oid,
            'status' => 'success',
            'total_amount' => '100',
            'hash' => base64_encode(hash_hmac('sha256', $oid.'s'.'success'.'100', 'k', true)),
        ])->assertOk()->assertSee('OK');
    }

    public function test_panelden_acilip_kapatilabilir(): void
    {
        $yonetici = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($yonetici)->test(SiteAyarlari::class)
            ->fillForm(['bakim_modu' => true])
            ->call('save');

        $this->assertTrue(config('shop.bakim_modu'));

        /*
         * GERILEME: (string) false === '' ve bos deger .env'ye dusuyordu —
         * perde panelden KAPATILAMAZDI.
         */
        Livewire::actingAs($yonetici)->test(SiteAyarlari::class)
            ->fillForm(['bakim_modu' => false])
            ->call('save');

        config(['shop.bakim_modu' => true]); // .env'de acik oldugunu varsay
        Setting::configeBindir();

        $this->assertFalse(config('shop.bakim_modu'));
    }
}
