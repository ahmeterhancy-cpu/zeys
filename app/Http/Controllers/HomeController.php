<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;

/**
 * Ana sayfa — PressMart home-2 bölüm sırası:
 * slayt → kategori daireleri → indirimdekiler → koleksiyon afişleri →
 * sekmeli ürünler → mağaza afişleri → özellik şeridi → küçük listeler.
 *
 * Her bölüm GERÇEK veriden beslenir, veri yoksa bölüm hiç çizilmez.
 * Referanstaki geri sayımlı "günün fırsatı" bilerek yok: gerçek bir süre
 * sınırı olmadan sayaç göstermek yanıltıcı ticari uygulamadır.
 */
class HomeController extends Controller
{
    public function __invoke()
    {
        $aktif = fn () => Product::query()->where('is_active', true)->kartIcin();

        $koleksiyonlar = Collection::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->get();

        $kategoriler = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->get();

        $yeniUrunler = $aktif()
            ->orderByDesc('created_at')
            ->orderBy('position')
            ->limit(8)
            ->get();

        // İndirim: en az bir aktif varyantın eski fiyatı satış fiyatından yüksek
        $indirimdekiler = $aktif()
            ->whereHas('variants', fn ($v) => $v->where('is_active', true)
                ->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price'))
            ->orderBy('position')
            ->limit(8)
            ->get();

        // Çok satanlar: ödenmiş siparişlerdeki adet
        $cokSatanlar = $aktif()
            ->withSum(['orderItems as satilan' => fn ($q) => $q->whereHas('order', fn ($o) => $o->where('payment_status', 'paid'))], 'quantity')
            ->orderByDesc('satilan')
            ->limit(8)
            ->get()
            ->filter(fn ($p) => (int) $p->satilan > 0)
            ->values();

        $begenilenler = $aktif()
            ->where('review_count', '>', 0)
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->limit(8)
            ->get();

        $oneCikanlar = $aktif()
            ->where('is_featured', true)
            ->orderBy('position')
            ->limit(3)
            ->get();

        /*
         * Panelden yönetilen slayt/afişler (Vitrin → Slayt ve Afişler).
         * Bir yer boşsa görünüm otomatik içeriğe döner.
         */
        $banner = fn (string $yer) => rescue(fn () => Banner::yayinda($yer)->get(), collect(), false);

        return view('vitrin.anasayfa', [
            'bannerSlayt' => $banner('slayt'),
            'bannerAfis' => $banner('afis')->take(3),
            'bannerGenis' => $banner('genis')->take(2),
            'koleksiyonlar' => $koleksiyonlar,
            // Slayt: görseli olan koleksiyonlar; yoksa markanın kendi slaytı
            'slaytlar' => $koleksiyonlar->whereNotNull('image')->take(3)->values(),
            'kategoriler' => $kategoriler,
            'yeniUrunler' => $yeniUrunler,
            'indirimdekiler' => $indirimdekiler,
            'cokSatanlar' => $cokSatanlar,
            'begenilenler' => $begenilenler,
            'oneCikanlar' => $oneCikanlar,
        ]);
    }
}
