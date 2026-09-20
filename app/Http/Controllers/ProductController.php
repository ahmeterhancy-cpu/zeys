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

        return view('vitrin.urun', [
            'urun' => $urun,
            'varyantlar' => $varyantlar,
            'renkGalerisi' => $renkGalerisi,
            'bedenTablosu' => $urun->resolvedSizeChart(),
            'genelGorseller' => $urun->media->whereNull('product_option_value_id')->values(),
        ]);
    }
}
