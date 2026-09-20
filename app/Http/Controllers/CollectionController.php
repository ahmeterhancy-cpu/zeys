<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Product;

class CollectionController extends Controller
{
    /** Tüm ürünler + koleksiyon şeritleri. */
    public function index()
    {
        $urunler = Product::query()
            ->where('is_active', true)
            ->with(['options.values'])
            ->orderBy('position')
            ->paginate(24);

        $koleksiyonlar = Collection::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->get();

        return view('vitrin.koleksiyonlar', compact('urunler', 'koleksiyonlar'));
    }

    public function show(string $slug)
    {
        $koleksiyon = Collection::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $urunler = $koleksiyon->products()
            ->where('is_active', true)
            ->with(['options.values'])
            ->orderBy('position')
            ->paginate(24);

        return view('vitrin.koleksiyon', compact('koleksiyon', 'urunler'));
    }
}
