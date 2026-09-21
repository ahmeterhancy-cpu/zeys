<?php

namespace Tests\Feature;

use App\Mail\ParolaSifirlama;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ParolaSifirlamaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_formlar_acilir_ve_giriste_baglanti_var(): void
    {
        $this->get('/parolami-unuttum')->assertOk();
        $this->get('/giris')->assertOk()->assertSee('Parolamı unuttum', false);
    }

    public function test_kayitli_adrese_turkce_markali_eposta_gider(): void
    {
        User::factory()->create(['email' => 'ayse@example.test']);

        $this->post('/parolami-unuttum', ['eposta' => 'ayse@example.test'])
            ->assertSessionHas('bilgi');

        Mail::assertSent(ParolaSifirlama::class, function (ParolaSifirlama $mail) {
            return $mail->hasTo('ayse@example.test')
                && str_contains($mail->baglanti, '/parola-sifirla/')
                && str_contains($mail->render(), 'Yeni parola belirle');
        });
    }

    public function test_kayitsiz_adres_icin_ayni_mesaj_ve_eposta_yok(): void
    {
        User::factory()->create(['email' => 'ayse@example.test']);

        $var = $this->post('/parolami-unuttum', ['eposta' => 'ayse@example.test']);
        $yok = $this->post('/parolami-unuttum', ['eposta' => 'hicyok@example.test']);

        // Hesabin varligi ele verilmemeli
        $this->assertSame(
            $var->getSession()->get('bilgi'),
            $yok->getSession()->get('bilgi')
        );

        Mail::assertSent(ParolaSifirlama::class, 1);
    }

    public function test_gecerli_jetonla_parola_degisir(): void
    {
        $kullanici = User::factory()->create(['email' => 'ayse@example.test']);
        $jeton = Password::createToken($kullanici);

        $this->post('/parola-sifirla', [
            'token' => $jeton,
            'eposta' => 'ayse@example.test',
            'parola' => 'YeniParola123',
            'parola_confirmation' => 'YeniParola123',
        ])->assertRedirect('/giris');

        $this->assertTrue(Hash::check('YeniParola123', $kullanici->fresh()->password));
    }

    public function test_jeton_ikinci_kez_kullanilamaz(): void
    {
        $kullanici = User::factory()->create(['email' => 'ayse@example.test']);
        $jeton = Password::createToken($kullanici);

        $veri = [
            'token' => $jeton,
            'eposta' => 'ayse@example.test',
            'parola' => 'YeniParola123',
            'parola_confirmation' => 'YeniParola123',
        ];

        $this->post('/parola-sifirla', $veri)->assertRedirect('/giris');

        $this->post('/parola-sifirla', array_merge($veri, [
            'parola' => 'BaskaParola456',
            'parola_confirmation' => 'BaskaParola456',
        ]))->assertSessionHasErrors('eposta');

        $this->assertTrue(Hash::check('YeniParola123', $kullanici->fresh()->password));
    }

    public function test_sahte_jeton_reddedilir(): void
    {
        $kullanici = User::factory()->create(['email' => 'ayse@example.test']);
        $eski = $kullanici->password;

        $this->post('/parola-sifirla', [
            'token' => 'uydurma-jeton',
            'eposta' => 'ayse@example.test',
            'parola' => 'YeniParola123',
            'parola_confirmation' => 'YeniParola123',
        ])->assertSessionHasErrors('eposta');

        $this->assertSame($eski, $kullanici->fresh()->password);
    }

    public function test_kisa_parola_reddedilir(): void
    {
        $kullanici = User::factory()->create(['email' => 'ayse@example.test']);

        $this->post('/parola-sifirla', [
            'token' => Password::createToken($kullanici),
            'eposta' => 'ayse@example.test',
            'parola' => 'kisa',
            'parola_confirmation' => 'kisa',
        ])->assertSessionHasErrors('parola');
    }
}
