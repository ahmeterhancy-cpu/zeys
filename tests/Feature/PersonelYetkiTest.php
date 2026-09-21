<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Widgets\MagazaOzeti;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersonelYetkiTest extends TestCase
{
    use RefreshDatabase;

    private User $personel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personel = User::factory()->create(['role' => 'staff']);
    }

    private function siparis(array $alanlar = []): Order
    {
        return Order::create(array_merge([
            'number' => 'ZEY-260921-0001',
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.test',
            'customer_phone' => '5551112233',
            'shipping_address' => ['city' => 'Edirne'],
            'grand_total' => 2890,
            'status' => 'paid',
            'payment_status' => 'paid',
        ], $alanlar));
    }

    public function test_personel_panele_girer_musteri_giremez(): void
    {
        $this->actingAs($this->personel)->get('/admin')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'customer']))->get('/admin')->assertForbidden();
    }

    public function test_personel_operasyon_ekranlarini_gorur(): void
    {
        foreach (['/admin/orders', '/admin/products', '/admin/return-requests', '/admin/stock-inquiries', '/admin/product-reviews'] as $yol) {
            $this->actingAs($this->personel)->get($yol)->assertOk();
        }
    }

    public function test_personel_yonetim_ekranlarina_giremez(): void
    {
        foreach ([
            '/admin/users', '/admin/coupons', '/admin/site-ayarlari', '/admin/satis-raporu',
            '/admin/legal-documents', '/admin/categories', '/admin/collections', '/admin/size-charts',
        ] as $yol) {
            $this->actingAs($this->personel)->get($yol)->assertForbidden();
        }
    }

    public function test_menude_yonetim_baglantilari_gorunmez(): void
    {
        $this->actingAs($this->personel)->get('/admin')
            ->assertSee('Siparişler')
            ->assertDontSee('Site Ayarları')
            ->assertDontSee('Satış Raporu')
            ->assertDontSee('Kullanıcılar');
    }

    public function test_personel_siparisi_kargolar_ama_iptal_edemez(): void
    {
        $siparis = $this->siparis();

        Livewire::actingAs($this->personel)->test(ListOrders::class)
            ->assertTableActionVisible('kargola', $siparis)
            ->assertTableActionHidden('iptal', $siparis);

        /*
         * Gizli eylemi ham Livewire çağrısıyla tetiklemek de işe yaramamalı
         * (->visible() yalnız gizlerdi; ->authorize() sunucuda da durdurur).
         */
        Livewire::actingAs($this->personel)->test(ListOrders::class)
            ->call('mountAction', 'iptal', [], ['table' => true, 'recordKey' => $siparis->getKey()])
            ->call('callMountedAction');

        $this->assertSame('paid', $siparis->fresh()->status);
    }

    public function test_yonetici_iptali_gorur(): void
    {
        $siparis = $this->siparis();

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ListOrders::class)
            ->assertTableActionVisible('iptal', $siparis);
    }

    public function test_personel_urun_silemez_csv_yukleyemez_fiyat_degistiremez(): void
    {
        $urun = Product::create(['name' => 'Triko', 'base_sku' => 'ZEYS-006', 'is_active' => true]);
        app(VariantMatrix::class)->generate($urun, ['Beden' => ['kind' => 'text', 'values' => ['M']]], 1790.00);

        Livewire::actingAs($this->personel)->test(ListProducts::class)
            ->assertActionHidden('csvIce')
            ->assertActionVisible('csvDisa');

        Livewire::actingAs($this->personel)->test(EditProduct::class, ['record' => $urun->id])
            ->assertActionHidden('delete');

        $varyant = $urun->variants->first();

        Livewire::actingAs($this->personel)
            ->test(VariantsRelationManager::class, ['ownerRecord' => $urun, 'pageClass' => EditProduct::class])
            ->mountTableAction('edit', $varyant)
            ->assertTableActionDataSet(['price' => '1790.00'])
            ->setTableActionData(['price' => 1, 'stock' => 7])
            ->callMountedTableAction();

        $this->assertSame('1790.00', $varyant->fresh()->price, 'Personel fiyatı değiştirememeli');
        $this->assertSame(7, $varyant->fresh()->stock, 'Stok değişebilmeli');
    }

    public function test_panoda_personel_ciroyu_gormez(): void
    {
        $this->siparis(['paid_at' => now()]);

        // Pano bileşenleri tembel yüklenir; bileşen doğrudan sınanır
        Livewire::actingAs($this->personel)->test(MagazaOzeti::class)->assertDontSee('TL ciro');
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(MagazaOzeti::class)->assertSee('TL ciro');
    }

    public function test_personel_bakim_perdesinden_gecer(): void
    {
        config(['shop.bakim_modu' => true]);

        $this->actingAs($this->personel)->get('/')->assertOk();
    }
}
