<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function category(string $slug)
    {
        $kategori = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        /*
         * Alt kategorilerin ürünleri de listeleniyor: "Üst Giyim"e
         * tıklayan müşteri altındaki "Gömlek" ürünlerini de görmeli.
         * Tek seviye iniliyor; daha derin ağaç şimdilik yok.
         */
        $kategoriIdleri = $kategori->children()->pluck('id')->push($kategori->id);

        $urunler = Product::query()
            ->where('is_active', true)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $kategoriIdleri))
            ->with(['options.values'])
            ->orderBy('position')
            ->paginate(24)
            ->withQueryString();

        return view('vitrin.kategori', compact('kategori', 'urunler'));
    }

    public function search(Request $request)
    {
        $veri = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $sorgu = trim($veri['q'] ?? '');

        $urunler = null;

        if ($sorgu !== '') {
            /*
             * Sade LIKE araması. Ürün sayısı birkaç yüzü geçerse tam
             * metin indeksi gerekecek; şimdilik gereksiz karmaşıklık.
             *
             * Varyant SKU'su da aranıyor — mağaza personeli elindeki
             * etiketten ürünü bulabilsin.
             */
            $kalip = '%'.mb_strtolower($sorgu).'%';

            $urunler = Product::query()
                ->where('is_active', true)
                ->where(function ($q) use ($kalip) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$kalip])
                        ->orWhereRaw('LOWER(COALESCE(short_description, \'\')) LIKE ?', [$kalip])
                        ->orWhereRaw('LOWER(COALESCE(base_sku, \'\')) LIKE ?', [$kalip])
                        ->orWhereHas('variants', fn ($v) => $v->whereRaw('LOWER(sku) LIKE ?', [$kalip]));
                })
                ->with(['options.values'])
                ->orderBy('position')
                ->paginate(24)
                ->withQueryString();
        }

        return view('vitrin.arama', compact('sorgu', 'urunler'));
    }
}
