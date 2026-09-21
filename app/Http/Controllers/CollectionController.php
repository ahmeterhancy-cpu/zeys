<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Product;
use App\Support\Katalog;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    /** Tüm ürünler (mağaza sayfası) + koleksiyon afişleri. */
    public function index(Request $request)
    {
        $urunler = Katalog::uygula(
            Product::query()->where('is_active', true)->kartIcin(),
            $request,
        )->paginate(24)->withQueryString();

        $koleksiyonlar = Collection::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->get();

        return view('vitrin.koleksiyonlar', compact('urunler', 'koleksiyonlar'));
    }

    public function show(Request $request, string $slug)
    {
        $koleksiyon = Collection::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $urunler = Katalog::uygula(
            $koleksiyon->products()->where('is_active', true)->kartIcin(),
            $request,
        )->paginate(24)->withQueryString();

        return view('vitrin.koleksiyon', compact('koleksiyon', 'urunler'));
    }
}
