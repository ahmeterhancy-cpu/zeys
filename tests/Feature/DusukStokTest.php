<?php

namespace Tests\Feature;

use App\Mail\BackInStock;
use App\Mail\DusukStok;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockInquiry;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderStock;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DusukStokTest extends TestCase
{
    use RefreshDatabase;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        config([
            'shop.dusuk_stok_esigi' => 3,
            'shop.siparis_bildirim_epostasi' => 'magaza@example.test',
        ]);

        $this->urun = Product::create(['name' => 'Keten Pantolon', 'base_sku' => 'ZEYS-002', 'is_active' => true]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['S', 'M']],
        ], 1200.00);
    }

    private function varyant(string $etiket = 'S'): ProductVariant
    {
        return $this->urun->variants()->with('optionValues.option')->get()->firstWhere('label', $etiket);
    }

    private function siparisVer(ProductVariant $variant, int $adet): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($variant, $adet);

        return app(Checkout::class)->place(
            ['name' => 'Ayşe Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Yılmaz', 'phone' => '5551112233', 'line1' => 'Cumhuriyet Mah. 1', 'district' => 'Merkez', 'city' => 'Edirne'],
        );
    }

    public function test_satisla_esige_inince_magazaya_bir_kez_yazilir(): void
    {
        $s = $this->varyant();
        $s->update(['stock' => 5]);

        app(OrderStock::class)->commit($this->siparisVer($s, 2)); // 5 → 3: eşik

        Mail::assertSent(DusukStok::class, fn (DusukStok $m) => $m->hasTo('magaza@example.test') && $m->variant->stock === 3);

        app(OrderStock::class)->commit($this->siparisVer($s->fresh(), 1)); // 3 → 2: zaten altında

        Mail::assertSent(DusukStok::class, 1);
    }

    public function test_odeme_sayfasini_acmak_uyari_uretmez(): void
    {
        $s = $this->varyant();
        $s->update(['stock' => 4]);

        $this->siparisVer($s, 3); // yalnız rezerv — raf stoğu 4

        Mail::assertNotSent(DusukStok::class);
    }

    public function test_tukenince_ayrica_yazilir(): void
    {
        $s = $this->varyant();
        $s->update(['stock' => 2]); // eşiğin altından başlıyor (5→2 değil, 0→2)

        Mail::assertNotSent(DusukStok::class);

        app(OrderStock::class)->commit($this->siparisVer($s, 2));

        Mail::assertSent(DusukStok::class, function (DusukStok $m) {
            return $m->variant->stock === 0
                && str_starts_with($m->envelope()->subject, 'Tükendi');
        });
    }

    public function test_adres_yoksa_gonderilmez(): void
    {
        config(['shop.siparis_bildirim_epostasi' => null]);

        $s = $this->varyant();
        $s->update(['stock' => 5]);
        $s->update(['stock' => 1]);

        Mail::assertNothingSent();
    }

    public function test_pasif_urun_icin_gonderilmez(): void
    {
        $this->urun->update(['is_active' => false]);

        $s = $this->varyant();
        $s->update(['stock' => 5]);
        $s->update(['stock' => 1]);

        Mail::assertNotSent(DusukStok::class);
    }

    public function test_geri_alinan_islemde_gonderilmez(): void
    {
        $s = $this->varyant();
        $s->update(['stock' => 5]);

        try {
            DB::transaction(function () use ($s) {
                $s->update(['stock' => 1]);
                throw new \RuntimeException('ödeme kaydı düştü');
            });
        } catch (\RuntimeException) {
        }

        Mail::assertNotSent(DusukStok::class);
        $this->assertSame(5, $s->fresh()->stock);
    }

    public function test_eposta_icerigi_gorunur(): void
    {
        $s = $this->varyant();
        $s->update(['stock' => 5]);
        $s->fresh()->update(['stock' => 3]);

        Mail::assertSent(DusukStok::class, function (DusukStok $m) {
            $html = $m->render();

            return str_contains($html, 'Keten Pantolon')
                && str_contains($html, $m->variant->sku)
                && str_contains($html, '/admin/products/'.$m->variant->product_id.'/edit');
        });
    }

    /**
     * GERİLEME: iptalde stok `increment()` ile geri ekleniyor; gözlemci
     * `saved`'ı dinlediği için ürün önbelleği tazelenmiyor ve bekleyen
     * müşteriye "stokta" postası gitmiyordu.
     */
    public function test_iptalde_geri_gelen_stok_onbellegi_tazeler_ve_bekleyene_yazar(): void
    {
        $s = $this->varyant();
        $s->update(['stock' => 1]);

        $order = $this->siparisVer($s, 1);
        app(OrderStock::class)->commit($order);

        $this->assertSame(0, $this->urun->fresh()->total_stock);

        StockInquiry::create(['product_variant_id' => $s->id, 'email' => 'bekleyen@example.test']);

        app(OrderStock::class)->restore($order->fresh());

        $this->assertSame(1, $this->urun->fresh()->total_stock, 'Vitrin önbelleği stoğun geri geldiğini görmeli');
        Mail::assertSent(BackInStock::class, fn (BackInStock $m) => $m->hasTo('bekleyen@example.test'));
    }

    /** GERİLEME: rezerv de `increment()` ile artıyor; önbellek satılabilir adedi tutuyor. */
    public function test_rezerv_onbellegi_tazeler(): void
    {
        $s = $this->varyant();
        $s->update(['stock' => 4]);
        $this->assertSame(4, $this->urun->fresh()->total_stock);

        $this->siparisVer($s->fresh(), 3);

        $this->assertSame(1, $this->urun->fresh()->total_stock);
    }
}
