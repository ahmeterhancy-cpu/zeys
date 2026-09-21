<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        $urun = Product::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'options.values',
                'variants.optionValues',
                'media',
                'categories.sizeChart',
                'sizeChart',
                'collection',
                'related' => fn ($q) => $q->where('is_active', true)->with('options.values'),
            ])
            ->firstOrFail();

        /*
         * Varyant tablosu tarayıcıya veri olarak gider; beden/renk seçimi
         * sunucuya gidip gelmeden çözülsün diye. Fiyat burada GÖSTERİM
         * amaçlıdır — sepete eklerken fiyat yeniden sunucuda okunur,
         * istemciden gelen tutara asla güvenilmez.
         */
        $varyantlar = $urun->variants
            ->where('is_active', true)
            ->map(fn ($v) => [
                'id' => $v->id,
                'degerler' => $v->optionValues->pluck('id')->values()->all(),
                'stok' => $v->available_stock,
                'fiyat' => (float) $v->price,
                'sku' => $v->sku,
            ])->values();

        // Renk seçilince değişecek galeri: değer kimliği => görsel yolları
        $renkGalerisi = $urun->media
            ->whereNotNull('product_option_value_id')
            ->groupBy('product_option_value_id')
            ->map(fn ($grup) => $grup->pluck('path')->values()->all());

        /*
         * Açılışta gösterilecek görseller: kapak + genel galeri.
         *
         * Önceden yalnızca genel galeri kullanılıyordu; kapak görseli ürün
         * sayfasında HİÇ görünmüyordu. Üstelik renk değişiminde görseli
         * değiştiren <img> ancak genel görsel varsa çiziliyordu — kapak +
         * renk fotoğrafı olan bir ürün "Z" yer tutucusuyla açılıyor, renk
         * seçince de hiçbir şey olmuyordu.
         *
         * Hiç genel görsel yoksa ilk renk fotoğrafı açılış görseli olur.
         */
        $genelYollar = collect([$urun->hero_image])
            ->merge($urun->media->whereNull('product_option_value_id')->pluck('path'))
            ->filter()
            ->unique()
            ->values();

        if ($genelYollar->isEmpty()) {
            $genelYollar = $urun->media->pluck('path')->take(1)->values();
        }

        // Yalnız onaylı yorumlar; en yeni 20'si (ürün sayfası sonsuz uzamasın)
        $yorumlar = $urun->review_count > 0
            ? $urun->reviews()->yayinda()->latest('approved_at')->limit(20)->get()
            : collect();

        return view('vitrin.urun', [
            'urun' => $urun,
            'yorumlar' => $yorumlar,
            'varyantlar' => $varyantlar,
            'renkGalerisi' => $renkGalerisi,
            'bedenTablosu' => $urun->resolvedSizeChart(),
            'genelYollar' => $genelYollar,
        ]);
    }
}
