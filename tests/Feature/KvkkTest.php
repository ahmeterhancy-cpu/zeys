<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockInquiry;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KvkkTest extends TestCase
{
    use RefreshDatabase;

    private User $kullanici;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kullanici = User::factory()->create([
            'email' => 'ayse@example.test',
            'password' => Hash::make('GuvenliParola1'),
            'role' => 'customer',
        ]);

        $urun = Product::create(['name' => 'Saten Midi Elbise', 'is_active' => true]);
        app(VariantMatrix::class)->generate($urun, ['Beden' => ['kind' => 'text', 'values' => ['M']]], 2890.00);
        $varyant = $urun->fresh()->variants->first();
        $varyant->update(['stock' => 5]);

        app(Cart::class)->add($varyant, 1);

        $this->order = app(Checkout::class)->place(
            ['name' => 'Ayşe', 'email' => 'ayse@example.test', 'phone' => '555'],
            ['name' => 'Ayşe', 'phone' => '555', 'line1' => 'X', 'district' => 'Y', 'city' => 'Edirne'],
            billing: ['invoice_type' => 'individual', 'tax_number' => '10000000146', 'city' => 'Edirne'],
            userId: $this->kullanici->id,
        );

        Address::create([
            'user_id' => $this->kullanici->id, 'name' => 'Ayşe', 'phone' => '555',
            'line1' => 'Ev adresi', 'district' => 'Merkez', 'city' => 'Edirne',
        ]);

        StockInquiry::create(['product_variant_id' => $varyant->id, 'email' => 'ayse@example.test']);
    }

    public function test_verilerim_sayfasi_acilir(): void
    {
        $this->actingAs($this->kullanici)->get('/hesap/verilerim')
            ->assertOk()
            ->assertSee('Verilerimi indir', false)
            ->assertSee('Silinmeyecekler', false);
    }

    public function test_veri_disa_aktarimi_tum_kisisel_verileri_icerir(): void
    {
        $yanit = $this->actingAs($this->kullanici)->get('/hesap/verilerim/indir')->assertOk();

        $this->assertStringContainsString('attachment', $yanit->headers->get('Content-Disposition'));

        $veri = $yanit->json();

        $this->assertSame('ayse@example.test', $veri['hesap']['eposta']);
        $this->assertCount(1, $veri['adresler']);
        $this->assertSame($this->order->number, $veri['siparisler'][0]['numara']);
        $this->assertSame('10000000146', $veri['siparisler'][0]['fatura']['tax_number']);
        $this->assertCount(1, $veri['stok_bildirimleri']);
    }

    public function test_baskasinin_verisi_disa_aktarilmaz(): void
    {
        $baska = User::factory()->create(['role' => 'customer']);

        $veri = $this->actingAs($baska)->get('/hesap/verilerim/indir')->json();

        $this->assertSame([], $veri['siparisler']);
        $this->assertSame([], $veri['adresler']);
    }

    public function test_hesap_silinir_siparis_saklanir(): void
    {
        $this->actingAs($this->kullanici)->delete('/hesap', [
            'parola' => 'GuvenliParola1',
            'onay' => '1',
        ])->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $this->kullanici->id]);
        $this->assertSame(0, Address::count());
        $this->assertSame(0, StockInquiry::count());

        // Siparis yasal saklama geregi SILINMEZ, hesaptan ayrilir
        $order = $this->order->fresh();
        $this->assertNotNull($order);
        $this->assertNull($order->user_id);
    }

    public function test_yanlis_parolayla_hesap_silinmez(): void
    {
        $this->actingAs($this->kullanici)->delete('/hesap', [
            'parola' => 'YanlisParola',
            'onay' => '1',
        ])->assertSessionHasErrors('parola');

        $this->assertDatabaseHas('users', ['id' => $this->kullanici->id]);
    }

    public function test_onay_kutusu_isaretlenmeden_silinmez(): void
    {
        $this->actingAs($this->kullanici)->delete('/hesap', [
            'parola' => 'GuvenliParola1',
        ])->assertSessionHasErrors('onay');

        $this->assertDatabaseHas('users', ['id' => $this->kullanici->id]);
    }

    public function test_yonetici_vitrinden_silinemez(): void
    {
        $yonetici = User::factory()->create(['role' => 'admin', 'password' => Hash::make('GuvenliParola1')]);

        $this->actingAs($yonetici)->delete('/hesap', [
            'parola' => 'GuvenliParola1',
            'onay' => '1',
        ])->assertSessionHasErrors('parola');

        $this->assertDatabaseHas('users', ['id' => $yonetici->id]);
    }

    public function test_giris_yapmadan_erisilemez(): void
    {
        $this->get('/hesap/verilerim/indir')->assertRedirect('/giris');
        $this->delete('/hesap')->assertRedirect('/giris');
    }
}
