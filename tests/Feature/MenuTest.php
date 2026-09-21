<?php

namespace Tests\Feature;

use App\Filament\Resources\MenuOgeleri\Pages\CreateMenuOgesi;
use App\Models\Category;
use App\Models\MenuOgesi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private function anaMenu(string $html): string
    {
        $this->assertSame(1, preg_match('#<ul class="ana-menu">(.+?)</ul>#s', $html, $m));

        return $m[1];
    }

    public function test_bosken_varsayilan_menuler(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $ana = $this->anaMenu($html);
        foreach (['Ana Sayfa', 'Koleksiyonlar', 'Yeni Gelenler', 'Sipariş Sorgula', 'İletişim'] as $etiket) {
            $this->assertStringContainsString($etiket, $ana);
        }
    }

    public function test_eklenen_bag_ana_menunun_yerini_alir_ve_sirali_gelir(): void
    {
        MenuOgesi::create(['konum' => 'ana', 'etiket' => 'Kampanya', 'baglanti' => '/koleksiyonlar?sirala=indirim', 'sira' => 2]);
        MenuOgesi::create(['konum' => 'ana', 'etiket' => 'Elbiseler', 'baglanti' => '/kategori/elbise', 'sira' => 1]);
        MenuOgesi::create(['konum' => 'ana', 'etiket' => 'Gizli', 'baglanti' => '/x', 'aktif' => false]);

        $ana = $this->anaMenu($this->get('/')->getContent());

        $this->assertStringNotContainsString('Yeni Gelenler', $ana, 'Varsayılanlar kalkmalı');
        $this->assertStringNotContainsString('Gizli', $ana);
        $this->assertLessThan(strpos($ana, 'Kampanya'), strpos($ana, 'Elbiseler'), 'Sıra uygulanmalı');
    }

    public function test_diger_konumlar_etkilenmez_ve_dis_baglanti_yeni_sekmede(): void
    {
        MenuOgesi::create(['konum' => 'alt-yardim', 'etiket' => 'Beden Rehberi', 'baglanti' => 'https://example.test/rehber', 'yeni_sekme' => true]);

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('Yeni Gelenler', $this->anaMenu($html));
        $this->assertMatchesRegularExpression('#href="https://example.test/rehber"\s+target="_blank" rel="noopener"\s*>Beden Rehberi#', $html);
    }

    public function test_hazir_sayfa_secince_alanlar_dolar(): void
    {
        $kat = Category::create(['name' => 'Elbise', 'is_active' => true]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CreateMenuOgesi::class)
            ->fillForm(['konum' => 'ana', 'hazir' => '/kategori/'.$kat->slug])
            ->assertSchemaStateSet(['baglanti' => '/kategori/'.$kat->slug, 'etiket' => 'Elbise'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('menu_ogeleri', ['etiket' => 'Elbise', 'baglanti' => '/kategori/elbise']);
    }

    public function test_gecersiz_baglanti_reddedilir(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(CreateMenuOgesi::class)
            ->fillForm(['konum' => 'ana', 'etiket' => 'X', 'baglanti' => 'javascript:alert(1)'])
            ->call('create')
            ->assertHasFormErrors(['baglanti']);
    }

    public function test_personel_menulere_giremez(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/admin/menuler')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/menuler')->assertOk();
    }
}
