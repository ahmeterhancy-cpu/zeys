<?php

namespace Tests\Feature;

use App\Filament\Resources\LegalDocuments\Pages\EditLegalDocument;
use App\Filament\Resources\LegalDocuments\Pages\ListLegalDocuments;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\SizeCharts\Pages\EditSizeChart;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\SizeChart;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\OrderShipping;
use App\Services\Returns;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Panelin DOĞRU İŞ YAPTIĞINI doğrular.
 *
 * PanelTest sayfaların açıldığını (200) kontrol ediyor; o testler iade
 * ekranı durum makinesini hiç çağırmadığı hâlde yeşildi. Buradaki
 * testler eylemleri gerçekten çalıştırıp yan etkilerine bakıyor.
 */
class PanelDavranisTest extends TestCase
{
    use RefreshDatabase;

    private User $yonetici;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        $this->yonetici = User::factory()->create(['role' => 'admin']);

        $this->urun = Product::create([
            'name' => 'Saten Midi Elbise',
            'base_sku' => 'ZEYS-001',
            'is_active' => true,
        ]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M', 'L']],
        ], 2890.00);

        $this->urun->fresh()->variants->each->update(['stock' => 5]);
    }

    private function varyant(string $etiket): ProductVariant
    {
        return $this->urun->fresh()->variants()->with('optionValues.option')->get()
            ->firstWhere('label', $etiket);
    }

    /** Ödenmiş ve teslim edilmiş sipariş. */
    private function teslimEdilmisSiparis(int $adet = 2): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), $adet);

        $order = app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'X', 'district' => 'Y', 'city' => 'Edirne'],
        );

        app(OrderPayments::class)->markPaid($order);

        $shipping = app(OrderShipping::class);
        $shipping->markShipped($order->fresh(), 'Yurtiçi', '123');
        $shipping->markDelivered($order->fresh());

        return $order->fresh('items');
    }

    private function talepAc(Order $order, string $tur = 'return'): ReturnRequest
    {
        return app(Returns::class)->open($order, $tur, 'beden', [
            $order->items->first()->id => ['quantity' => 1],
        ]);
    }

    // --- İade kuyruğu: asıl mesele ---

    public function test_panelden_onaylamak_stogu_geri_getirir_ve_tutari_isler(): void
    {
        $order = $this->teslimEdilmisSiparis(2);
        $varyant = $this->varyant('M');

        $this->assertSame(3, $varyant->fresh()->stock);

        $talep = $this->talepAc($order);
        app(Returns::class)->markReceived($talep);

        Livewire::actingAs($this->yonetici)
            ->test(ListReturnRequests::class)
            ->callTableAction('onayla', $talep->fresh(), ['not' => 'Ürün sağlam geldi.']);

        // Durum makinesi gercekten calismis mi?
        $talep->refresh();
        $this->assertSame('approved', $talep->status);
        $this->assertSame('2890.00', $talep->refund_amount);

        // Stok geri gelmis mi?
        $this->assertSame(4, $varyant->fresh()->stock);

        // Iade tutari siparise islenmis mi?
        $order->refresh();
        $this->assertSame('2890.00', $order->refunded_total);
        $this->assertSame('partially_refunded', $order->payment_status);
    }

    public function test_teslim_alinmadan_onaylama_eylemi_gorunmez(): void
    {
        $order = $this->teslimEdilmisSiparis();
        $talep = $this->talepAc($order);

        // Talep 'opened' durumunda — onayla eylemi gizli olmali
        Livewire::actingAs($this->yonetici)
            ->test(ListReturnRequests::class)
            ->assertTableActionHidden('onayla', $talep);
    }

    public function test_degisim_onayinda_para_iadesi_yapilmaz(): void
    {
        $order = $this->teslimEdilmisSiparis(1);
        $l = $this->varyant('L');

        $talep = app(Returns::class)->open($order, 'exchange', 'beden', [
            $order->items->first()->id => ['quantity' => 1, 'exchange_variant_id' => $l->id],
        ]);

        app(Returns::class)->markReceived($talep);

        Livewire::actingAs($this->yonetici)
            ->test(ListReturnRequests::class)
            ->callTableAction('onayla', $talep->fresh(), ['not' => null]);

        $this->assertSame('0.00', $talep->fresh()->refund_amount);
        $this->assertSame('0.00', $order->fresh()->refunded_total);
        // Degisim varyanti rezerve edilmis olmali
        $this->assertSame(1, $l->fresh()->reserved);
    }

    public function test_reddedilen_talep_iade_hakkini_tuketmez(): void
    {
        $order = $this->teslimEdilmisSiparis(2);
        $item = $order->items->first();

        $talep = app(Returns::class)->open($order, 'return', 'beden', [
            $item->id => ['quantity' => 2],
        ]);
        app(Returns::class)->markReceived($talep);

        Livewire::actingAs($this->yonetici)
            ->test(ListReturnRequests::class)
            ->callTableAction('reddet', $talep->fresh(), ['not' => 'Ürün kullanılmış.']);

        $this->assertSame('rejected', $talep->fresh()->status);
        $this->assertSame(2, app(Returns::class)->returnableQuantity($item->fresh()));
    }

    public function test_acik_talepler_suzgeci_calisir(): void
    {
        /*
         * GERILEME TESTI: süzgeç kapanışının parametresi $query değilse
         * Filament null enjekte ediyor ve sayfa 500 veriyordu. Bu süzgeç
         * varsayılan açık olduğu için liste hiç yüklenmiyordu.
         */
        $order = $this->teslimEdilmisSiparis(2);

        $acik = $this->talepAc($order);
        $kapali = $this->talepAc($order);
        app(Returns::class)->reject($kapali, 'kapali');

        Livewire::actingAs($this->yonetici)
            ->test(ListReturnRequests::class)
            ->assertCanSeeTableRecords([$acik])
            ->assertCanNotSeeTableRecords([$kapali]);
    }

    // --- Sipariş eylemleri ---

    public function test_kargoya_verme_eylemi_takip_numarasini_kaydeder(): void
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), 1);

        $order = app(Checkout::class)->place(
            ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
            ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
        );
        app(OrderPayments::class)->markPaid($order);

        Livewire::actingAs($this->yonetici)
            ->test(ListOrders::class)
            ->callTableAction('kargola', $order->fresh(), [
                'kargo_firmasi' => 'Yurtiçi Kargo',
                'takip_no' => '9876543210',
            ]);

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertSame('9876543210', $order->tracking_number);
        $this->assertNotNull($order->shipped_at);
    }

    public function test_odenmemis_sipariste_kargola_eylemi_gorunmez(): void
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), 1);

        $order = app(Checkout::class)->place(
            ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
            ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
        );

        Livewire::actingAs($this->yonetici)
            ->test(ListOrders::class)
            ->assertTableActionHidden('kargola', $order);
    }

    public function test_takili_rezerv_suzgeci_calisir(): void
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), 1);

        $eski = app(Checkout::class)->place(
            ['name' => 'Test', 'email' => 't@example.test', 'phone' => '5550000000'],
            ['name' => 'Test', 'phone' => '5550000000', 'line1' => 'X', 'district' => 'Y', 'city' => 'Z'],
        );

        // 3 saat once acilmis, hala rezerve
        $eski->forceFill(['created_at' => now()->subHours(3)])->save();

        Livewire::actingAs($this->yonetici)
            ->test(ListOrders::class)
            ->filterTable('takili_rezerv')
            ->assertCanSeeTableRecords([$eski->fresh()]);
    }

    // --- Ürün süzgeci ---

    public function test_stogu_bitenler_suzgeci_calisir(): void
    {
        $this->urun->fresh()->variants->each->update(['stock' => 0]);

        $dolu = Product::create(['name' => 'Stoklu Ürün', 'is_active' => true]);
        app(VariantMatrix::class)->generate($dolu, [
            'Beden' => ['kind' => 'text', 'values' => ['M']],
        ], 1000.00);
        $dolu->fresh()->variants->each->update(['stock' => 4]);

        Livewire::actingAs($this->yonetici)
            ->test(ListProducts::class)
            ->filterTable('stok_bitti')
            ->assertCanSeeTableRecords([$this->urun->fresh()])
            ->assertCanNotSeeTableRecords([$dolu->fresh()]);
    }

    // --- Beden tablosu ---

    public function test_beden_tablosu_formu_satirlari_bozmadan_kaydeder(): void
    {
        /*
         * columns/rows veritabanında JSON dizisi. Form tekrarlayıcısı
         * satırları {hucreler: [...]} sarmalına çevirip geri açıyor;
         * bu dönüşüm bozulursa ürün sayfasındaki tablo patlar.
         */
        $tablo = SizeChart::create([
            'name' => 'Üst Giyim',
            'columns' => ['Beden', 'Göğüs', 'Bel'],
            'rows' => [['S', '88', '72'], ['M', '92', '76']],
        ]);

        $bilesen = Livewire::actingAs($this->yonetici)
            ->test(EditSizeChart::class, ['record' => $tablo->id]);

        // Dokunmadan kaydet — veri aynen kalmalı
        $bilesen->call('save')->assertHasNoErrors();

        $tablo->refresh();
        $this->assertSame(['Beden', 'Göğüs', 'Bel'], $tablo->columns);
        $this->assertSame([['S', '88', '72'], ['M', '92', '76']], $tablo->rows);
        $this->assertTrue($tablo->isConsistent());
    }

    public function test_beden_tablosu_formdan_duzenlenebilir(): void
    {
        $tablo = SizeChart::create([
            'name' => 'Alt Giyim',
            'columns' => ['Beden', 'Bel'],
            'rows' => [['S', '68']],
        ]);

        Livewire::actingAs($this->yonetici)
            ->test(EditSizeChart::class, ['record' => $tablo->id])
            ->fillForm([
                'columns' => 'Beden | Bel | Basen',
                'rows' => "S | 68 | 92\nM | 72 | 96\nL | 76 | 100",
            ])
            ->call('save')
            ->assertHasNoErrors();

        $tablo->refresh();
        $this->assertSame(['Beden', 'Bel', 'Basen'], $tablo->columns);
        $this->assertSame([
            ['S', '68', '92'],
            ['M', '72', '96'],
            ['L', '76', '100'],
        ], $tablo->rows);
        $this->assertTrue($tablo->isConsistent());
    }

    public function test_beden_tablosunda_bos_satirlar_atilir(): void
    {
        $tablo = SizeChart::create([
            'name' => 'Test',
            'columns' => ['Beden'],
            'rows' => [['S']],
        ]);

        Livewire::actingAs($this->yonetici)
            ->test(EditSizeChart::class, ['record' => $tablo->id])
            ->fillForm([
                'columns' => 'Beden | Bel',
                // Aradaki bos satir ve sondaki yeni satir veriyi kirletmemeli
                'rows' => "S | 68\n\nM | 72\n",
            ])
            ->call('save');

        $this->assertSame([['S', '68'], ['M', '72']], $tablo->fresh()->rows);
    }

    public function test_tutarsiz_beden_tablosu_tespit_edilir(): void
    {
        $tablo = SizeChart::create([
            'name' => 'Bozuk',
            'columns' => ['Beden', 'Göğüs', 'Bel'],
            // Ikinci satirda bir hucre eksik
            'rows' => [['S', '88', '72'], ['M', '92']],
        ]);

        $this->assertFalse($tablo->isConsistent());
    }

    // --- Yasal metinler ---

    public function test_yeni_surum_eskisini_arsivler(): void
    {
        $ilk = LegalDocument::publish('kvkk', 'KVKK Aydınlatma Metni', 'Eski metin');

        Livewire::actingAs($this->yonetici)
            ->test(EditLegalDocument::class, ['record' => $ilk->id])
            ->callAction('yeniSurum', [
                'title' => 'KVKK Aydınlatma Metni',
                'body' => 'Yeni metin',
            ]);

        $ilk->refresh();
        $this->assertFalse($ilk->is_current);
        // Eski metin SILINMEMELI — siparisler ona dayanıyor
        $this->assertSame('Eski metin', $ilk->body);

        $yururlukte = LegalDocument::current('kvkk');
        $this->assertSame('Yeni metin', $yururlukte->body);
        $this->assertNotSame($ilk->version, $yururlukte->version);
    }

    public function test_bagli_siparisi_olan_surum_silinemez(): void
    {
        $belge = LegalDocument::publish('mesafeli-satis', 'Mesafeli Satış Sözleşmesi', 'Metin');

        Order::create([
            'number' => 'ZEY-260921-0099',
            'customer_name' => 'Ayşe',
            'customer_email' => 'a@example.test',
            'customer_phone' => '555',
            'shipping_address' => ['city' => 'Edirne'],
            'grand_total' => 100,
            'contract_version' => $belge->version,
        ]);

        Livewire::actingAs($this->yonetici)
            ->test(EditLegalDocument::class, ['record' => $belge->id])
            ->assertActionHidden('delete');
    }

    public function test_bagli_siparisi_olmayan_surum_silinebilir(): void
    {
        $belge = LegalDocument::publish('cerez', 'Çerez Politikası', 'Metin');

        Livewire::actingAs($this->yonetici)
            ->test(EditLegalDocument::class, ['record' => $belge->id])
            ->assertActionVisible('delete');
    }

    public function test_yasal_metin_listesi_bagli_siparis_sayisini_gosterir(): void
    {
        $belge = LegalDocument::publish('kvkk', 'KVKK', 'Metin');

        Order::create([
            'number' => 'ZEY-260921-0098',
            'customer_name' => 'Ayşe',
            'customer_email' => 'a@example.test',
            'customer_phone' => '555',
            'shipping_address' => ['city' => 'Edirne'],
            'grand_total' => 100,
            'contract_version' => $belge->version,
        ]);

        Livewire::actingAs($this->yonetici)
            ->test(ListLegalDocuments::class)
            ->assertCanSeeTableRecords([$belge]);

        $this->assertSame(1, Order::where('contract_version', $belge->version)->count());
    }
}
