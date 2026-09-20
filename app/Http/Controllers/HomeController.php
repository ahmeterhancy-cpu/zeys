<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Product;

class HomeController extends Controller
{
    public function __invoke()
    {
        /*
         * `options.values` önceden yüklenir: ürün kartı her ürün için renk
         * noktalarını çiziyor, yüklenmezse 12 ürünlük ızgara 25+ sorgu atar.
         */
        $yeniUrunler = Product::query()
            ->where('is_active', true)
            ->with(['options.values'])
            ->orderByDesc('is_featured')
            ->orderBy('position')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $koleksiyonlar = Collection::query()
            ->where('is_active', true)
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->get();

        return view('vitrin.anasayfa', compact('yeniUrunler', 'koleksiyonlar'));
    }
}
