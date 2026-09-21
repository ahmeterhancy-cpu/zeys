<?php

namespace Tests\Feature;

use App\Filament\Pages\SatisRaporuSayfasi;
use App\Models\Order;
use App\Models\User;
use App\Services\SatisRaporu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SatisRaporuTest extends TestCase
{
    use RefreshDatabase;

    private int $sira = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function siparis(array $alanlar, array $kalemler = []): Order
    {
        $this->sira++;

        $order = Order::create(array_merge([
            'number' => 'ZEY-260921-'.str_pad((string) $this->sira, 4, '0', STR_PAD_LEFT),
            'customer_name' => 'Ayşe',
            'customer_email' => 'a@example.test',
            'customer_phone' => '555',
            'shipping_address' => ['city' => 'Edirne'],
            'status' => 'paid',
            'payment_status' => 'paid',
            'shipping_total' => 0,
        ], $alanlar));

        foreach ($kalemler as [$sku, $ad, $varyant, $adet, $birim]) {
            $order->items()->create([
                'sku' => $sku, 'name' => $ad, 'variant_label' => $varyant,
                'quantity' => $adet, 'unit_price' => $birim, 'line_total' => $adet * $birim,
            ]);
        }

        return $order;
    }

    public function test_ozet_odeme_tarihine_gore_hesaplanir(): void
    {
        $this->siparis(['grand_total' => 2989, 'shipping_total' => 99, 'paid_at' => '2026-09-03 10:00'],
            [['Z-1-M', 'Saten Elbise', 'M', 1, 2890]]);
        $this->siparis(['grand_total' => 5780, 'refunded_total' => 2890, 'paid_at' => '2026-09-20 23:59'],
            [['Z-1-M', 'Saten Elbise', 'M', 1, 2890], ['Z-2-S', 'Keten Ceket', 'S', 1, 2890]]);

        // Dönem dışı, ödenmemiş: sayılmamalı
        $this->siparis(['grand_total' => 999, 'paid_at' => '2026-08-31 23:59'], [['Z-9', 'Eski', null, 5, 199.8]]);
        $this->siparis(['grand_total' => 999, 'payment_status' => 'pending', 'status' => 'pending',
            'created_at' => '2026-09-10 10:00'], [['Z-9', 'Eski', null, 5, 199.8]]);
        /*
         * Aralığın son gecesi oluşturulup ertesi gün ödenen sipariş bu
         * döneme yazılmamalı: dönem ödeme gününe göre.
         */
        $this->siparis(['grand_total' => 500, 'created_at' => '2026-09-21 23:58', 'paid_at' => '2026-09-22 00:03']);

        $r = app(SatisRaporu::class)->hesapla('2026-09-01', '2026-09-21');

        $this->assertSame(2, $r['siparis']);
        $this->assertSame(3, $r['adet']);
        $this->assertSame(8769.0, $r['brut']);
        $this->assertSame(99.0, $r['kargo']);
        $this->assertSame(2890.0, $r['iade']);
        $this->assertSame(5879.0, $r['net']);
        $this->assertSame(4384.5, $r['ortalama']);

        $this->assertSame('Z-1-M', $r['cokSatan'][0]['sku']);
        $this->assertSame(2, $r['cokSatan'][0]['adet']);

        $this->assertCount(21, $r['gunluk']);
        $gun20 = collect($r['gunluk'])->firstWhere('gun', '2026-09-20');
        $this->assertSame(1, $gun20['siparis']);
        $this->assertSame(5780.0, $gun20['ciro']);
    }

    public function test_ters_girilen_aralik_duzeltilir_ve_bos_aralik_sifirdir(): void
    {
        $r = app(SatisRaporu::class)->hesapla('2026-09-10', '2026-09-01');

        $this->assertSame('2026-09-01', $r['baslangic']->toDateString());
        $this->assertSame(0, $r['siparis']);
        $this->assertSame(0.0, $r['ortalama']);
        $this->assertSame([], $r['cokSatan']);
    }

    public function test_csv_turkce_excel_bicimindedir(): void
    {
        $this->siparis(['grand_total' => 2989, 'paid_at' => '2026-09-03 10:00'], [['Z-1-M', 'Saten Elbise', 'M', 1, 2890]]);

        $csv = app(SatisRaporu::class)->csv(app(SatisRaporu::class)->hesapla('2026-09-01', '2026-09-21'));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Brüt ciro (KDV dahil)";2989,00', $csv);
        $this->assertStringContainsString('Z-1-M;"Saten Elbise";M;1;2890,00', $csv);
    }

    public function test_panel_sayfasi_acilir_ve_aralik_degisir(): void
    {
        $this->siparis(['grand_total' => 2989, 'paid_at' => '2026-09-21 10:00'], [['Z-1-M', 'Saten Elbise', 'M', 1, 2890]]);
        $yonetici = User::factory()->create(['role' => 'admin']);

        $this->actingAs($yonetici)->get('/admin/satis-raporu')
            ->assertOk()
            ->assertSee('Net ciro')
            ->assertSee('Saten Elbise')
            ->assertSee('2.989,00 TL', false);

        Livewire::actingAs($yonetici)->test(SatisRaporuSayfasi::class)
            ->call('aralik', 'gecenay')
            ->assertSet('filtre.baslangic', fn ($v) => str_starts_with((string) $v, '2026-08-01'))
            ->assertSet('filtre.bitis', fn ($v) => str_starts_with((string) $v, '2026-08-31'))
            ->assertDontSee('Saten Elbise')
            ->callAction('csv')
            ->assertFileDownloaded('zeys-satis-20260801-20260831.csv');
    }

    public function test_musteri_rapora_erisemez(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get('/admin/satis-raporu')
            ->assertForbidden();
    }
}
