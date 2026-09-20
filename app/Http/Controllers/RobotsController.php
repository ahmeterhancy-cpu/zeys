<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * robots.txt — statik dosya yerine denetleyiciden.
 *
 * İki sebep:
 *  1. Sitemap adresinde alan adı çalışma anında çözülüyor; statik
 *     dosyada elle yazılsa yanlış alan adı canlıya çıkabilirdi.
 *  2. Bakım kipinde ya da canlı olmayan ortamda taramayı tamamen
 *     kapatabiliyoruz — hazır olmayan bir site dizine girmemeli.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        // Canlı değilse ya da bakım kipindeyse hiçbir şey taranmasın
        if (! app()->environment('production') || config('shop.bakim_modu')) {
            $govde = "User-agent: *\nDisallow: /\n";

            return response($govde, 200)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $satirlar = [
            'User-agent: *',
            '',
            '# Kişisel veri ve işlem sayfaları dizine girmemeli',
            'Disallow: /sepet',
            'Disallow: /odeme',
            'Disallow: /siparis/',
            'Disallow: /siparis-sorgula',
            'Disallow: /admin',
            'Disallow: /paytr/',
            '',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $satirlar)."\n", 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
