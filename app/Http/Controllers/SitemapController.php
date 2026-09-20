<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\LegalDocument;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $girdiler = [];

        $girdiler[] = ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'];
        $girdiler[] = ['loc' => route('collections.index'), 'priority' => '0.9', 'changefreq' => 'daily'];
        $girdiler[] = ['loc' => route('contact'), 'priority' => '0.4', 'changefreq' => 'yearly'];

        foreach (Collection::where('is_active', true)->get() as $koleksiyon) {
            $girdiler[] = [
                'loc' => route('collections.show', $koleksiyon->slug),
                'lastmod' => $koleksiyon->updated_at?->toAtomString(),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        foreach (Product::where('is_active', true)->get() as $urun) {
            $girdiler[] = [
                'loc' => route('products.show', $urun->slug),
                'lastmod' => $urun->updated_at?->toAtomString(),
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ];
        }

        foreach (LegalDocument::where('is_current', true)->get() as $belge) {
            $girdiler[] = [
                'loc' => route('legal', $belge->slug),
                'lastmod' => $belge->published_at?->toAtomString(),
                'priority' => '0.2',
                'changefreq' => 'yearly',
            ];
        }

        /*
         * Sepet, kasa, sipariş sorgulama ve panel BİLEREK yok:
         * dizine girmeleri hem anlamsız hem de sorgu sayfası üzerinden
         * gereksiz tarama trafiği doğurur.
         */
        return response()
            ->view('sitemap', ['girdiler' => $girdiler])
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
}
