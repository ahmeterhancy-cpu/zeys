<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteAyarlari;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Filament\Resources\StockInquiries\Pages\ListStockInquiries;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\MagazaOzeti;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductOptionValue;
use App\Models\Setting;
use App\Models\StockInquiry;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PanelEklerTest extends TestCase
{
    use RefreshDatabase;

    private User $yonetici;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('public');

        $this->yonetici = User::factory()->create(['role' => 'admin']);

        $this->urun = Product::create(['name' => 'Saten Midi Elbise', 'base_sku' => 'ZEYS-001', 'is_active' => true]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
            'Renk' => ['kind' => 'color', 'values' => [
                ['value' => 'Siyah', 'color_hex' => '#111111'],
                ['value' => 'Bej', 'color_hex' => '#d8c9ae'],
            ]],
        ], 2890.00);

        $this->urun->fresh()->variants->each->update(['stock' => 5]);
    }

    private function renk(string $ad): ProductOptionValue
    {
        return ProductOptionValue::where('value', $ad)->firstOrFail();
    }

    // --- Galeri ---

    public function test_toplu_yukleme_gorselleri_renge_atar_ve_kapak_yapar(): void
    {
        $siyah = $this->renk('Siyah');

        Livewire::actingAs($this->yonetici)
            ->test(MediaRelationManager::class, [
                'ownerRecord' => $this->urun,
                'pageClass' => EditProduct::class,
            ])
            ->callTableAction('topluYukle', data: [
                'dosyalar' => [
                    UploadedFile::fake()->image('on.jpg'),
                    UploadedFile::fake()->image('arka.jpg'),
                ],
                'renk' => $siyah->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, ProductMedia::where('product_option_value_id', $siyah->id)->count());

        // Kapak bossa ilk yuklenen kapak olmali — yoksa kart "Z" ile kalir
        $this->assertNotNull($this->urun->fresh()->hero_image);

        /*
         * GERILEME: FileUpload diski belirtilmemisti; Filament .env'deki
         * FILESYSTEM_DISK'e (yerelde "local" = gizli depo) yaziyordu ve
         * vitrin storage/ altindan okudugu icin fotograf hic gorunmuyordu.
         */
        Storage::disk('public')->assertExists($this->urun->fresh()->hero_image);
    }

    public function test_urun_sayfasi_yalniz_kapak_gorseli_varken_onu_gosterir(): void
    {
        /*
         * GERILEME: urun sayfasi kapak gorselini hic kullanmiyordu;
         * yalniz kapagi olan urun "Z" yer tutucusuyla aciliyordu.
         */
        $this->urun->update(['hero_image' => 'urunler/kapak.jpg']);

        $this->get('/urun/saten-midi-elbise')
            ->assertOk()
            ->assertSee('storage/urunler/kapak.jpg', false)
            ->assertSee('id="galeri-ana"', false);
    }

    public function test_yalniz_renk_fotografi_varken_ana_gorsel_cizilir(): void
    {
        /*
         * GERILEME: renk degisimini yapan <img> ancak genel gorsel varsa
         * ciziliyordu; yalniz renk fotografi olan urunde renk secince
         * hicbir sey olmuyordu.
         */
        ProductMedia::create([
            'product_id' => $this->urun->id,
            'product_option_value_id' => $this->renk('Bej')->id,
            'path' => 'urunler/galeri/bej.jpg',
        ]);

        $this->get('/urun/saten-midi-elbise')
            ->assertOk()
            ->assertSee('id="galeri-ana"', false)
            ->assertSee('data-taban=', false);
    }

    // --- Stok talepleri ---

    public function test_stok_talepleri_listesi_bekleyenleri_gosterir(): void
    {
        $varyant = $this->urun->fresh()->variants->first();
        $varyant->update(['stock' => 0]);

        $bekleyen = StockInquiry::create(['product_variant_id' => $varyant->id, 'email' => 'a@example.test']);
        $bildirilen = StockInquiry::create(['product_variant_id' => $varyant->id, 'email' => 'b@example.test', 'notified_at' => now()]);

        Livewire::actingAs($this->yonetici)
            ->test(ListStockInquiries::class)
            ->assertCanSeeTableRecords([$bekleyen])
            ->assertCanNotSeeTableRecords([$bildirilen]);
    }

    public function test_stok_talebi_panelden_olusturulamaz(): void
    {
        $this->actingAs($this->yonetici)->get('/admin/stock-inquiries/create')->assertNotFound();
    }

    // --- Kullanıcılar ---

    public function test_yonetici_kendini_silemez(): void
    {
        User::factory()->create(['role' => 'admin']); // son yonetici degil

        Livewire::actingAs($this->yonetici)
            ->test(ListUsers::class)
            ->assertTableActionHidden('delete', $this->yonetici);
    }

    public function test_son_yonetici_silinemez(): void
    {
        $tekYonetici = $this->yonetici;
        $baskaYonetici = User::factory()->create(['role' => 'admin']);

        // Ikisi varken biri silinebilir
        Livewire::actingAs($tekYonetici)
            ->test(ListUsers::class)
            ->assertTableActionVisible('delete', $baskaYonetici);

        $baskaYonetici->delete();

        // Tek yonetici kalinca, baska bir yonetici hesabiyla bakilsa bile silinemez
        $musteri = User::factory()->create(['role' => 'customer']);
        $this->assertFalse(UserResource::silinebilirMi($tekYonetici->fresh()));
        $this->assertTrue(UserResource::silinebilirMi($musteri));
    }

    public function test_yonetici_kendi_rolunu_dusuremez(): void
    {
        /*
         * Alan istemcide devre disi ama devre disi alan elle acilip
         * gonderilebilir; sunucu tarafi da engellemeli.
         */
        Livewire::actingAs($this->yonetici)
            ->test(EditUser::class, ['record' => $this->yonetici->id])
            ->set('data.role', 'customer')
            ->call('save');

        $this->assertSame('admin', $this->yonetici->fresh()->role);
    }

    public function test_bos_parola_mevcut_parolayi_degistirmez(): void
    {
        $musteri = User::factory()->create(['role' => 'customer']);
        $eskiHash = $musteri->password;

        Livewire::actingAs($this->yonetici)
            ->test(EditUser::class, ['record' => $musteri->id])
            ->fillForm(['name' => 'Yeni Ad', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($eskiHash, $musteri->fresh()->password);
        $this->assertSame('Yeni Ad', $musteri->fresh()->name);
    }

    // --- Site ayarları ---

    public function test_ayarlar_kaydedilince_kargo_hesabi_degisir(): void
    {
        Livewire::actingAs($this->yonetici)
            ->test(SiteAyarlari::class)
            ->fillForm([
                'kargo_ucret' => '49',
                'kargo_ucretsiz_esigi' => '5000',
                'dusuk_stok_esigi' => '2',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(49.0, config('shop.kargo.ucret'));

        // Sepet yeni degerleri kullanmali (2890 < 5000 → ucretli)
        app(Cart::class)->add($this->urun->fresh()->variants->first(), 1);
        $this->assertSame(49.0, app(Cart::class)->shipping());
    }

    public function test_ayar_sonraki_istekte_de_gecerli(): void
    {
        /*
         * GERILEME (baska projeden): ayar kaydedilince onbellek
         * tazelenmezse panelde degistirilen deger sitede gorunmuyordu.
         */
        Setting::kaydet(['kargo_ucret' => '59']);

        // Yeni bir istek gibi: config'i .env varsayilanina geri al, sonra bindir
        config(['shop.kargo.ucret' => 99.0]);
        Setting::configeBindir();

        $this->assertSame(59.0, config('shop.kargo.ucret'));
    }

    public function test_haritada_olmayan_ayar_configi_degistiremez(): void
    {
        Setting::kaydet(['app.debug' => 'true', 'kargo_ucret' => '10']);

        $this->assertDatabaseMissing('settings', ['key' => 'app.debug']);
    }

    public function test_yasal_yer_tutucular_yeni_surumle_doldurulur(): void
    {
        $ilk = LegalDocument::publish(
            'mesafeli-satis',
            'Mesafeli Satış Sözleşmesi',
            '<p>SATICI: [SATICI UNVANI GİRİLMEDİ] — ELLE EKLENMİŞ CÜMLE</p>'
        );

        Setting::kaydet(['satici_unvan' => 'Zeys Tekstil Ltd. Şti.']);

        Livewire::actingAs($this->yonetici)
            ->test(SiteAyarlari::class)
            ->callAction('yasalDoldur');

        $yururlukte = LegalDocument::current('mesafeli-satis');

        $this->assertNotSame($ilk->id, $yururlukte->id, 'Yeni surum yayimlanmali');
        $this->assertStringContainsString('Zeys Tekstil Ltd. Şti.', $yururlukte->body);
        // Yoneticinin elle ekledigi metin korunmali
        $this->assertStringContainsString('ELLE EKLENMİŞ CÜMLE', $yururlukte->body);
        // Eski surum yerinde degismemeli
        $this->assertStringContainsString('[SATICI UNVANI GİRİLMEDİ]', $ilk->fresh()->body);
    }

    // --- Pano ---

    public function test_pano_acilir_ve_ozet_hesaplanir(): void
    {
        $this->actingAs($this->yonetici)->get('/admin')->assertOk();

        Livewire::actingAs($this->yonetici)
            ->test(MagazaOzeti::class)
            ->assertSee('Kargolanacak')
            ->assertSee('Takılı rezerv');
    }

    // --- Takılı rezerv temizliği ---

    private function siparis(): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->urun->fresh()->variants->first(), 2);

        return app(Checkout::class)->place(
            ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
            ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
        );
    }

    public function test_eski_yarim_odemenin_rezervi_birakilir(): void
    {
        $order = $this->siparis();
        $varyant = $this->urun->fresh()->variants->first();

        $this->assertSame(2, $varyant->fresh()->reserved);

        $order->forceFill(['created_at' => now()->subHours(3)])->save();

        $this->artisan('zeys:rezerv-temizle')->assertSuccessful();

        $this->assertSame(0, $varyant->fresh()->reserved);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('none', $order->fresh()->stock_state);
    }

    public function test_yeni_odemenin_rezervine_dokunulmaz(): void
    {
        $order = $this->siparis();

        // Musteri su an PayTR sayfasinda — rezervi bozulmamali
        $this->artisan('zeys:rezerv-temizle')->assertSuccessful();

        $this->assertSame('reserved', $order->fresh()->stock_state);
    }

    public function test_temizlenen_siparise_gec_odeme_gelirse_stok_yeniden_ayrilir(): void
    {
        $order = $this->siparis();
        $varyant = $this->urun->fresh()->variants->first();

        $order->forceFill(['created_at' => now()->subHours(3)])->save();
        $this->artisan('zeys:rezerv-temizle');

        // Gec gelen "basarili" callback
        app(OrderPayments::class)->markPaid($order->fresh());

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('committed', $order->stock_state);
        // Stok gercekten dustu — ayni parca iki kez satilmaz
        $this->assertSame(3, $varyant->fresh()->stock);
    }

    public function test_gec_odemede_stok_kalmamissa_siparis_incelemeye_duser(): void
    {
        $order = $this->siparis();
        $varyant = $this->urun->fresh()->variants->first();

        $order->forceFill(['created_at' => now()->subHours(3)])->save();
        $this->artisan('zeys:rezerv-temizle');

        // Bu arada stok baskasina satildi
        $varyant->fresh()->update(['stock' => 0]);

        app(OrderPayments::class)->markPaid($order->fresh());

        $order->refresh();
        $this->assertSame('paid', $order->payment_status, 'Para geldi, kayit edilmeli');
        $this->assertSame('cancelled', $order->status, 'Kargolanacaklar listesine girmemeli');
        $this->assertStringContainsString('GECİKMELİ ÖDEME', $order->admin_note);
        $this->assertSame(0, $varyant->fresh()->stock, 'Stok eksiye dusmemeli');
    }
}
