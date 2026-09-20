<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use RefreshDatabase;

    private User $yonetici;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yonetici = User::factory()->create(['role' => 'admin']);
    }

    public function test_musteri_hesabi_panele_giremez(): void
    {
        // FilamentUser::canAccessPanel yalnizca role=admin'e izin verir
        $musteri = User::factory()->create(['role' => 'customer']);

        $this->actingAs($musteri)->get('/admin/products')->assertForbidden();
    }

    public function test_giris_yapmadan_panele_girilemez(): void
    {
        $this->get('/admin')->assertRedirect();
        $this->get('/admin/products')->assertRedirect();
    }

    public function test_panel_giris_sayfasi_marka_adini_gosterir(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Zeys Fashion House');
    }

    public function test_urun_listesi_acilir(): void
    {
        $urun = Product::create(['name' => 'Saten Midi Elbise', 'is_active' => true]);

        app(VariantMatrix::class)->generate($urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
        ], 2890.00);

        $this->actingAs($this->yonetici)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('Saten Midi Elbise');
    }

    public function test_urun_duzenleme_sayfasi_acilir(): void
    {
        $urun = Product::create(['name' => 'Keten Gömlek', 'is_active' => true]);

        $this->actingAs($this->yonetici)
            ->get('/admin/products/'.$urun->id.'/edit')
            ->assertOk()
            ->assertSee('Keten Gömlek');
    }

    public function test_kombinasyon_uretme_eylemi_varyant_olusturur(): void
    {
        $urun = Product::create(['name' => 'Krep Bluz', 'base_sku' => 'ZEYS-005', 'is_active' => true]);

        // Eksenler formdan girilmiş gibi
        $beden = $urun->options()->create(['name' => 'Beden', 'kind' => 'text', 'position' => 0]);
        $beden->values()->create(['value' => 'S', 'position' => 0]);
        $beden->values()->create(['value' => 'M', 'position' => 1]);

        $renk = $urun->options()->create(['name' => 'Renk', 'kind' => 'color', 'position' => 1]);
        $renk->values()->create(['value' => 'Siyah', 'color_hex' => '#111111', 'position' => 0]);

        $this->assertSame(0, $urun->variants()->count());

        Livewire::actingAs($this->yonetici)
            ->test(EditProduct::class, ['record' => $urun->id])
            ->callAction('kombinasyonUret', ['varsayilan_fiyat' => 1290])
            ->assertHasNoActionErrors();

        // 2 beden x 1 renk
        $this->assertSame(2, $urun->fresh()->variants()->count());
        $this->assertSame('1290.00', $urun->fresh()->variants()->first()->price);
    }

    public function test_eksen_yokken_kombinasyon_uretilmez(): void
    {
        $urun = Product::create(['name' => 'Eksensiz', 'is_active' => true]);

        Livewire::actingAs($this->yonetici)
            ->test(EditProduct::class, ['record' => $urun->id])
            ->callAction('kombinasyonUret', ['varsayilan_fiyat' => 100]);

        $this->assertSame(0, $urun->fresh()->variants()->count());
    }

    public function test_varyant_tablosu_kombinasyon_etiketini_gosterir(): void
    {
        $urun = Product::create(['name' => 'Triko', 'base_sku' => 'ZEYS-006', 'is_active' => true]);

        app(VariantMatrix::class)->generate($urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M', 'L']],
            'Renk' => ['kind' => 'color', 'values' => [['value' => 'Bej', 'color_hex' => '#d8c9ae']]],
        ], 1790.00);

        Livewire::actingAs($this->yonetici)
            ->test(VariantsRelationManager::class, [
                'ownerRecord' => $urun->fresh(),
                'pageClass' => EditProduct::class,
            ])
            ->assertCanSeeTableRecords($urun->fresh()->variants);
    }

    public function test_siparis_listesi_acilir(): void
    {
        Order::create([
            'number' => 'ZEY-260921-0001',
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.test',
            'customer_phone' => '5551112233',
            'shipping_address' => ['city' => 'Edirne'],
            'grand_total' => 2890.00,
        ]);

        $this->actingAs($this->yonetici)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('ZEY-260921-0001')
            ->assertSee('Ayşe Yılmaz');
    }

    public function test_iade_listesi_acilir(): void
    {
        $this->actingAs($this->yonetici)->get('/admin/return-requests')->assertOk();
    }

    public function test_beden_tablolari_ve_yasal_metinler_acilir(): void
    {
        $this->actingAs($this->yonetici)->get('/admin/size-charts')->assertOk();
        $this->actingAs($this->yonetici)->get('/admin/legal-documents')->assertOk();
        $this->actingAs($this->yonetici)->get('/admin/categories')->assertOk();
        $this->actingAs($this->yonetici)->get('/admin/collections')->assertOk();
        $this->actingAs($this->yonetici)->get('/admin/coupons')->assertOk();
    }

    public function test_yonetici_komutu_hesap_olusturur(): void
    {
        $this->artisan('zeys:yonetici', [
            '--ad' => 'Test Yönetici',
            '--eposta' => 'yeni@example.test',
            '--parola' => 'CokGuvenliParola1',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'yeni@example.test']);
    }

    public function test_kisa_parola_reddedilir(): void
    {
        $this->artisan('zeys:yonetici', [
            '--ad' => 'Test',
            '--eposta' => 'kisa@example.test',
            '--parola' => 'kisa',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'kisa@example.test']);
    }
}
