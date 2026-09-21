<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bakım perdesi — panelden açılıp kapanır (Site Ayarları).
 *
 * Önceden `shop.bakim_modu` ayarı vardı ama yalnızca robots.txt ona
 * bakıyordu; açsan site kapanmazdı — yanıltıcı bir ayardı.
 *
 * Perde açıkken:
 *  - Ziyaretçi 503 + "yakında" sayfası görür (Retry-After ile; arama
 *    motorları siteyi kaldırmaz, geçici sayar).
 *  - Yönetici siteyi NORMAL görür (önizleme) ve panele girebilir.
 *  - PayTR bildirimleri ETKİLENMEZ: routes/paytr.php `web` grubunun
 *    dışında, bu ara katman orada hiç çalışmıyor. Perde açıkken gelen
 *    bir ödeme yine işlenir.
 *
 * TUZAK: Filament Livewire'ı rastgele önekle servis ediyor
 * (/livewire-172643c6/update). Geçiş listesinde `livewire*` olmalı;
 * `livewire/*` EŞLEŞMEZ ve perde açıkken panele giriş yapılamaz —
 * üstelik yanlış parolada bile hata çıkmaz (referans projede saatler yedi).
 */
class BakimPerdesi
{
    /** Perdeden geçen yollar. */
    private const GECIS = [
        'admin', 'admin/*',
        'livewire*',
        'filament/*',
        'build/*', 'img/*', 'css/*', 'js/*', 'fonts/*', 'storage/*',
        'robots.txt', 'favicon.ico',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('shop.bakim_modu')) {
            return $next($request);
        }

        if ($request->is(...self::GECIS)) {
            return $next($request);
        }

        // Yönetici siteyi olduğu gibi görür — yayına almadan önce bakabilsin
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        return response()
            ->view('vitrin.bakim', [], 503)
            ->header('Retry-After', '3600');
    }
}
