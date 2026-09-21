<?php

namespace Tests\Feature;

use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Models\Banner;
use App\Models\Collection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_panelden_slayt_eklenir_ve_gorsel_public_diske_yazilir(): void
    {
        Storage::fake('public');
        $yonetici = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($yonetici)->test(CreateBanner::class)
            ->fillForm([
                'yer' => 'slayt',
                'ust_metin' => 'Sonbahar',
                'baslik' => 'Triko Sezonu',
                'dugme_metni' => 'Keşfet',
                'baglanti' => '/koleksiyonlar',
                'gorsel' => UploadedFile::fake()->image('slayt.jpg', 1920, 800),
                'aktif' => true,
                'sira' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::sole();
        Storage::disk('public')->assertExists($banner->gorsel);

        $this->get('/')->assertOk()
            ->assertSee('Triko Sezonu')
            ->assertSee('Sonbahar')
            ->assertSee('Keşfet')
            ->assertSee('storage/'.$banner->gorsel, false);
    }

    public function test_gecersiz_baglanti_reddedilir(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CreateBanner::class)
            ->fillForm(['yer' => 'afis', 'baslik' => 'X', 'baglanti' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['baglanti']);
    }

    public function test_panel_slayti_koleksiyon_slaytinin_yerini_alir(): void
    {
        Collection::create(['name' => 'Yaz 26', 'is_active' => true, 'image' => 'vitrin/yaz.jpg']);

        $this->get('/')->assertSee('Yeni koleksiyon');

        Banner::create(['yer' => 'slayt', 'baslik' => 'Kampanya']);

        $this->get('/')->assertSee('Kampanya')->assertDontSee('Yeni koleksiyon');
    }

    public function test_takvim_ve_aktiflik_uygulanir(): void
    {
        Banner::create(['yer' => 'afis', 'baslik' => 'Pasif Afiş', 'aktif' => false]);
        Banner::create(['yer' => 'afis', 'baslik' => 'Bitmiş Kampanya', 'bitis' => now()->subDay()]);
        Banner::create(['yer' => 'afis', 'baslik' => 'Gelecek Kampanya', 'baslangic' => now()->addDay()]);
        Banner::create(['yer' => 'afis', 'baslik' => 'Yayındaki Afiş', 'baslangic' => now()->subDay(), 'bitis' => now()->addDay()]);

        $this->get('/')
            ->assertSee('Yayındaki Afiş')
            ->assertDontSee('Pasif Afiş')
            ->assertDontSee('Bitmiş Kampanya')
            ->assertDontSee('Gelecek Kampanya');
    }

    public function test_bos_iken_otomatik_icerik_kalir(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Zarafet', false)          // marka slaytı
            ->assertSee('Deneyerek alın', false);  // mağaza afişi
    }

    public function test_genis_afis_panelden_degisir_ve_dis_baglanti_yeni_sekmede_acilir(): void
    {
        Banner::create(['yer' => 'genis', 'baslik' => 'Instagram Çekilişi', 'baglanti' => 'https://instagram.com/zeysfashionhouse', 'dugme_metni' => 'Katıl']);

        $html = $this->get('/')->assertSee('Instagram Çekilişi')->assertDontSee('Deneyerek alın')->getContent();

        $this->assertMatchesRegularExpression('#href="https://instagram.com/zeysfashionhouse"\s+rel="noopener" target="_blank"#', $html);
    }

    public function test_personel_slayt_ekranina_giremez(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/admin/banners')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/banners')->assertOk();
    }
}
