<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HesapTest extends TestCase
{
    use RefreshDatabase;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->urun = Product::create(['name' => 'Saten Midi Elbise', 'is_active' => true]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
        ], 2890.00);

        $this->urun->fresh()->variants->each->update(['stock' => 5]);
    }

    private function siparisVer(string $eposta, ?int $userId = null): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->urun->fresh()->variants->first(), 1);

        $order = app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => $eposta, 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'X', 'district' => 'Y', 'city' => 'Edirne'],
            userId: $userId,
        );

        app(OrderPayments::class)->markPaid($order);

        return $order->fresh();
    }

    // --- Kayıt ve giriş ---

    public function test_hesap_olusturulabilir(): void
    {
        $this->post('/kayit', [
            'ad' => 'Ayşe Yılmaz',
            'eposta' => 'ayse@example.test',
            'parola' => 'GuvenliParola1',
            'parola_confirmation' => 'GuvenliParola1',
        ])->assertRedirect('/hesap');

        $this->assertDatabaseHas('users', [
            'email' => 'ayse@example.test',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
    }

    public function test_yeni_hesaba_gecmis_misafir_siparisleri_baglanir(): void
    {
        /*
         * Musteri once misafir olarak siparis verip sonra hesap acarsa
         * gecmisini kaybetmis hissetmemeli.
         */
        $misafirSiparis = $this->siparisVer('ayse@example.test');
        $this->assertNull($misafirSiparis->user_id);

        $this->post('/kayit', [
            'ad' => 'Ayşe Yılmaz',
            'eposta' => 'ayse@example.test',
            'parola' => 'GuvenliParola1',
            'parola_confirmation' => 'GuvenliParola1',
        ]);

        $this->assertNotNull($misafirSiparis->fresh()->user_id);
    }

    public function test_baskasinin_siparisi_kayitta_baglanmaz(): void
    {
        $baskasi = $this->siparisVer('baskasi@example.test');

        $this->post('/kayit', [
            'ad' => 'Ayşe',
            'eposta' => 'ayse@example.test',
            'parola' => 'GuvenliParola1',
            'parola_confirmation' => 'GuvenliParola1',
        ]);

        $this->assertNull($baskasi->fresh()->user_id);
    }

    public function test_ayni_eposta_ile_ikinci_hesap_acilmaz(): void
    {
        User::factory()->create(['email' => 'ayse@example.test']);

        $this->post('/kayit', [
            'ad' => 'Ayşe',
            'eposta' => 'ayse@example.test',
            'parola' => 'GuvenliParola1',
            'parola_confirmation' => 'GuvenliParola1',
        ])->assertSessionHasErrors('eposta');
    }

    public function test_kisa_parola_reddedilir(): void
    {
        $this->post('/kayit', [
            'ad' => 'Ayşe',
            'eposta' => 'ayse@example.test',
            'parola' => 'kisa',
            'parola_confirmation' => 'kisa',
        ])->assertSessionHasErrors('parola');

        $this->assertGuest();
    }

    public function test_giris_yapilabilir(): void
    {
        User::factory()->create([
            'email' => 'ayse@example.test',
            'password' => Hash::make('GuvenliParola1'),
        ]);

        $this->post('/giris', [
            'eposta' => 'ayse@example.test',
            'parola' => 'GuvenliParola1',
        ])->assertRedirect('/hesap');

        $this->assertAuthenticated();
    }

    public function test_yanlis_parola_hesabin_varligini_ele_vermez(): void
    {
        User::factory()->create([
            'email' => 'ayse@example.test',
            'password' => Hash::make('GuvenliParola1'),
        ]);

        $varOlan = $this->post('/giris', [
            'eposta' => 'ayse@example.test',
            'parola' => 'YanlisParola',
        ])->assertSessionHasErrors('eposta');

        $olmayan = $this->post('/giris', [
            'eposta' => 'hicyok@example.test',
            'parola' => 'YanlisParola',
        ])->assertSessionHasErrors('eposta');

        $this->assertSame(
            $varOlan->getSession()->get('errors')->first('eposta'),
            $olmayan->getSession()->get('errors')->first('eposta')
        );

        $this->assertGuest();
    }

    public function test_cikis_yapilabilir(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post('/cikis')->assertRedirect('/');

        $this->assertGuest();
    }

    // --- Hesap sayfaları ---

    public function test_giris_yapmadan_hesap_acilmaz(): void
    {
        $this->get('/hesap')->assertRedirect('/giris');
        $this->get('/hesap/adresler')->assertRedirect('/giris');
    }

    public function test_siparis_gecmisi_listelenir(): void
    {
        $kullanici = User::factory()->create(['email' => 'ayse@example.test']);
        $siparis = $this->siparisVer('ayse@example.test', $kullanici->id);

        $this->actingAs($kullanici)
            ->get('/hesap')
            ->assertOk()
            ->assertSee($siparis->number)
            ->assertSee('2.890,00 TL');
    }

    public function test_baskasinin_siparisi_gorunmez(): void
    {
        $kullanici = User::factory()->create();
        $baskasi = User::factory()->create();

        $siparis = $this->siparisVer('baskasi@example.test', $baskasi->id);

        $this->actingAs($kullanici)
            ->get('/hesap')
            ->assertOk()
            ->assertDontSee($siparis->number);
    }

    public function test_baskasinin_siparis_detayi_404(): void
    {
        $kullanici = User::factory()->create();
        $baskasi = User::factory()->create();

        $siparis = $this->siparisVer('baskasi@example.test', $baskasi->id);

        // 403 degil 404: siparisin var oldugunu bile ele vermeyelim
        $this->actingAs($kullanici)
            ->get('/hesap/siparis/'.$siparis->number)
            ->assertNotFound();
    }

    public function test_kendi_siparis_detayi_acilir(): void
    {
        $kullanici = User::factory()->create();
        $siparis = $this->siparisVer('ayse@example.test', $kullanici->id);

        $this->actingAs($kullanici)
            ->get('/hesap/siparis/'.$siparis->number)
            ->assertOk()
            ->assertSee($siparis->number)
            ->assertSee('Saten Midi Elbise');
    }

    // --- Adresler ---

    public function test_adres_eklenebilir(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->post('/hesap/adresler', [
            'baslik' => 'Ev',
            'ad' => 'Ayşe Yılmaz',
            'telefon' => '5551112233',
            'adres' => 'Cumhuriyet Mah. 1. Sokak No:5',
            'ilce' => 'Merkez',
            'il' => 'Edirne',
            'varsayilan' => '1',
        ])->assertRedirect('/hesap/adresler');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $kullanici->id,
            'city' => 'Edirne',
            'is_default' => true,
        ]);
    }

    public function test_tek_varsayilan_adres_olabilir(): void
    {
        $kullanici = User::factory()->create();

        $eski = Address::create([
            'user_id' => $kullanici->id,
            'name' => 'Ayşe', 'phone' => '555', 'line1' => 'A',
            'district' => 'B', 'city' => 'C', 'is_default' => true,
        ]);

        $this->actingAs($kullanici)->post('/hesap/adresler', [
            'ad' => 'Ayşe', 'telefon' => '555', 'adres' => 'D',
            'ilce' => 'E', 'il' => 'F', 'varsayilan' => '1',
        ]);

        $this->assertFalse($eski->fresh()->is_default);
        $this->assertSame(1, Address::where('is_default', true)->count());
    }

    public function test_baskasinin_adresi_silinemez(): void
    {
        $kullanici = User::factory()->create();
        $baskasi = User::factory()->create();

        $adres = Address::create([
            'user_id' => $baskasi->id,
            'name' => 'Başkası', 'phone' => '555', 'line1' => 'A',
            'district' => 'B', 'city' => 'C',
        ]);

        $this->actingAs($kullanici)
            ->delete('/hesap/adresler/'.$adres->id)
            ->assertNotFound();

        $this->assertDatabaseHas('addresses', ['id' => $adres->id]);
    }

    public function test_giris_yapmis_kullanici_giris_sayfasina_gidemez(): void
    {
        $kullanici = User::factory()->create();

        $this->actingAs($kullanici)->get('/giris')->assertRedirect();
    }
}
