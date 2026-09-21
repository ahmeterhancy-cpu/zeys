<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductReviews\Pages\ListProductReviews;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\OrderShipping;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class UrunYorumTest extends TestCase
{
    use RefreshDatabase;

    private Product $urun;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->urun = Product::create(['name' => 'Saten Midi Elbise', 'base_sku' => 'ZEYS-001', 'is_active' => true]);

        app(VariantMatrix::class)->generate($this->urun, [
            'Beden' => ['kind' => 'text', 'values' => ['M', 'L']],
        ], 2890.00);

        $this->urun->fresh()->variants->each->update(['stock' => 5]);
    }

    private function varyant(string $etiket): ProductVariant
    {
        return $this->urun->fresh()->variants()->with('optionValues.option')->get()->firstWhere('label', $etiket);
    }

    /** İki beden aynı üründen: tek değerlendirme hakkı doğurmalı. */
    private function siparis(bool $teslim = true, ?User $kullanici = null): Order
    {
        app(Cart::class)->clear();
        app(Cart::class)->add($this->varyant('M'), 1);
        app(Cart::class)->add($this->varyant('L'), 1);

        $order = app(Checkout::class)->place(
            ['name' => 'Ayşe Nur Yılmaz', 'email' => 'ayse@example.test', 'phone' => '5551112233'],
            ['name' => 'Ayşe Nur Yılmaz', 'phone' => '5551112233', 'line1' => 'Cumhuriyet Mah. 1', 'district' => 'Merkez', 'city' => 'Edirne'],
            userId: $kullanici?->id,
        );

        app(OrderPayments::class)->markPaid($order);

        if ($teslim) {
            app(OrderShipping::class)->markShipped($order->fresh(), 'Yurtiçi', '123');
            app(OrderShipping::class)->markDelivered($order->fresh());
        }

        return $order->fresh('items');
    }

    private function formAdresi(Order $order): string
    {
        $html = $this->get(URL::signedRoute('order.show', ['order' => $order->number]))->getContent();
        $this->assertSame(1, preg_match('/action="([^"]+)"\s+class="degerlendir-formu"/', $html, $m), 'Değerlendirme formu sayfada yok');

        return html_entity_decode($m[1]);
    }

    private function yorumYaz(Order $order, array $alanlar = [])
    {
        return $this->post($this->formAdresi($order), array_merge([
            'product_id' => $this->urun->id,
            'puan' => 5,
            'baslik' => 'Kalıbı harika',
            'yorum' => 'Kumaşı kaliteli, bedeni tam oldu.',
        ], $alanlar));
    }

    public function test_teslim_edilen_sipariste_urun_bir_kez_listelenir_ve_yorum_yazilir(): void
    {
        $order = $this->siparis();

        $sayfa = $this->get(URL::signedRoute('order.show', ['order' => $order->number]));
        $sayfa->assertSee('Ürünleri Değerlendirin');
        $this->assertSame(1, substr_count($sayfa->getContent(), 'class="degerlendir-formu"'), 'İki beden = tek form');

        $this->yorumYaz($order)->assertRedirect()->assertSessionHas('bilgi');

        $yorum = ProductReview::sole();
        $this->assertSame('pending', $yorum->status);
        $this->assertSame('Ayşe Y.', $yorum->author_name, 'Tam ad yayımlanmamalı; soyadın baş harfi');
        $this->assertSame(5, $yorum->rating);

        // Onaysız yorum ürün puanını etkilemez
        $this->assertSame(0, $this->urun->fresh()->review_count);
        $this->assertNull($this->urun->fresh()->rating);
    }

    public function test_teslim_edilmemis_sipariste_form_yok_ve_yazilamaz(): void
    {
        $order = $this->siparis(teslim: false);

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertDontSee('Ürünleri Değerlendirin');

        $this->post(URL::signedRoute('order.review', ['order' => $order->number]), [
            'product_id' => $this->urun->id, 'puan' => 5, 'yorum' => 'Daha gelmedi ama güzel.',
        ])->assertSessionHas('hata');

        $this->assertSame(0, ProductReview::count());
    }

    public function test_imzasiz_adresle_yazilamaz(): void
    {
        $order = $this->siparis();

        $this->post('/siparis/'.$order->number.'/degerlendirme', [
            'product_id' => $this->urun->id, 'puan' => 5, 'yorum' => 'Sipariş numarasını tahmin ettim.',
        ])->assertForbidden();
    }

    public function test_sipariste_olmayan_urune_yazilamaz(): void
    {
        $baska = Product::create(['name' => 'Keten Ceket', 'base_sku' => 'ZEYS-009', 'is_active' => true]);
        $order = $this->siparis();

        $this->yorumYaz($order, ['product_id' => $baska->id])->assertSessionHasErrors('product_id');

        $this->assertSame(0, ProductReview::count());
    }

    public function test_ayni_urune_ikinci_yorum_yazilamaz_ve_form_kaybolur(): void
    {
        $order = $this->siparis();
        $adres = $this->formAdresi($order);

        $this->yorumYaz($order);

        $this->post($adres, [
            'product_id' => $this->urun->id, 'puan' => 1, 'yorum' => 'İkinci kez yazıyorum, puanı düşüreyim.',
        ])->assertSessionHasErrors('yorum');

        $this->assertSame(1, ProductReview::count());

        $this->get(URL::signedRoute('order.show', ['order' => $order->number]))
            ->assertSee('Onay bekliyor')
            ->assertDontSee('class="degerlendir-formu"', false);
    }

    public function test_gecersiz_puan_ve_kisa_yorum_reddedilir(): void
    {
        $order = $this->siparis();

        $this->yorumYaz($order, ['puan' => 6])->assertSessionHasErrors('puan');
        $this->yorumYaz($order, ['yorum' => 'Güzel'])->assertSessionHasErrors('yorum');

        $this->assertSame(0, ProductReview::count());
    }

    public function test_onaylaninca_urun_sayfasinda_ve_json_ldde_gorunur(): void
    {
        $this->yorumYaz($this->siparis());

        // Onay öncesi: vitrinde ve yapısal veride yok
        $this->get('/urun/'.$this->urun->slug)
            ->assertDontSee('Kumaşı kaliteli')
            ->assertDontSee('aggregateRating', false);

        ProductReview::sole()->update(['status' => 'approved', 'approved_at' => now()]);

        $urun = $this->urun->fresh();
        $this->assertSame(1, $urun->review_count);
        $this->assertSame('5.00', $urun->rating);

        $sayfa = $this->get('/urun/'.$this->urun->slug)
            ->assertSee('Kumaşı kaliteli, bedeni tam oldu.')
            ->assertSee('Ayşe Y.')
            ->assertSee('1 değerlendirme');

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $sayfa->getContent(), $m));
        $ld = json_decode($m[1], true);

        $this->assertSame('5.0', $ld['aggregateRating']['ratingValue']);
        $this->assertSame(1, $ld['aggregateRating']['reviewCount']);
        $this->assertSame('Ayşe Y.', $ld['review'][0]['author']['name']);
    }

    public function test_puan_ortalamasi_yalniz_onaylilardan_ve_silmede_tazelenir(): void
    {
        $a = $this->siparis();
        $b = $this->siparis();
        $c = $this->siparis();

        $this->yorumYaz($a, ['puan' => 5]);
        $this->yorumYaz($b, ['puan' => 4]);
        $this->yorumYaz($c, ['puan' => 1]);

        ProductReview::where('order_id', '!=', $c->id)->get()
            ->each->update(['status' => 'approved', 'approved_at' => now()]);

        $this->assertSame('4.50', $this->urun->fresh()->rating);
        $this->assertSame(2, $this->urun->fresh()->review_count);

        ProductReview::where('order_id', $a->id)->sole()->delete();

        $this->assertSame('4.00', $this->urun->fresh()->rating);
        $this->assertSame(1, $this->urun->fresh()->review_count);
    }

    public function test_panelden_yayimlanir_ve_reddedilir(): void
    {
        $this->yorumYaz($this->siparis());
        $yorum = ProductReview::sole();
        $yonetici = User::factory()->create(['role' => 'admin']);

        $this->actingAs($yonetici)->get('/admin/product-reviews')->assertOk()->assertSee('Kumaşı kaliteli');

        Livewire::actingAs($yonetici)->test(ListProductReviews::class)
            ->callTableAction('yayimla', $yorum);

        $this->assertSame('approved', $yorum->fresh()->status);
        $this->assertNotNull($yorum->fresh()->approved_at);
        $this->assertSame(1, $this->urun->fresh()->review_count);

        Livewire::actingAs($yonetici)->test(ListProductReviews::class)
            ->set('tableFilters.status.value', null)
            ->callTableAction('reddet', $yorum);

        $this->assertSame('rejected', $yorum->fresh()->status);
        $this->assertSame(0, $this->urun->fresh()->review_count);
        $this->assertNull($this->urun->fresh()->rating);
    }

    public function test_panelden_toplu_silme_puani_tazeler(): void
    {
        $this->yorumYaz($this->siparis());
        ProductReview::sole()->update(['status' => 'approved', 'approved_at' => now()]);
        $yonetici = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($yonetici)->test(ListProductReviews::class)
            ->set('tableFilters.status.value', null)
            ->callTableBulkAction('delete', ProductReview::all());

        $this->assertSame(0, ProductReview::count());
        $this->assertSame(0, $this->urun->fresh()->review_count);
    }

    public function test_hesap_silinince_yorumlar_da_silinir_ve_disa_aktarimda_gorunur(): void
    {
        $musteri = User::factory()->create(['role' => 'customer', 'password' => bcrypt('gizli-parola-1')]);
        $order = $this->siparis(kullanici: $musteri);
        $this->assertSame($musteri->id, $order->user_id);

        $this->yorumYaz($order);
        ProductReview::sole()->update(['status' => 'approved', 'approved_at' => now()]);

        $json = $this->actingAs($musteri)->get('/hesap/verilerim/indir')->json();
        $this->assertSame('Kumaşı kaliteli, bedeni tam oldu.', $json['siparisler'][0]['degerlendirmeler'][0]['yorum']);

        $this->actingAs($musteri)->delete('/hesap', ['parola' => 'gizli-parola-1', 'onay' => '1'])->assertRedirect('/');

        $this->assertSame(0, ProductReview::count());
        $this->assertSame(0, $this->urun->fresh()->review_count);
    }

    public function test_gorunen_ad(): void
    {
        $this->assertSame('Ayşe Y.', ProductReview::gorunenAd('Ayşe Nur Yılmaz'));
        $this->assertSame('Ömer Ş.', ProductReview::gorunenAd('  ömer   şahin '));
        $this->assertSame('Cem', ProductReview::gorunenAd('Cem'));
        $this->assertSame('İpek I.', ProductReview::gorunenAd('ipek ılgaz'));
        $this->assertSame('Müşteri', ProductReview::gorunenAd('   '));
    }
}
