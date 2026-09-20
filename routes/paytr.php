<?php

use App\Http\Controllers\PaymentReturnController;
use App\Http\Controllers\PayTrCallbackController;
use Illuminate\Support\Facades\Route;

/*
 * PayTR uçları — BİLEREK `web` grubunun DIŞINDA.
 *
 * İkisi de oturum taşımaz ve taşımamalıdır:
 *
 * 1) paytr/callback — PayTR'nin sunucusundan gelir, tarayıcı yok, çerez yok.
 *
 * 2) odeme/donus — müşterinin tarayıcısından gelir ama iFrame İÇİNDEN
 *    POST edilir. Oturum çerezi SameSite=Lax olduğu için bu istekte
 *    GÖNDERİLMEZ. StartSession bunu "oturumu yok" sanıp BOŞ BİR OTURUM
 *    açar ve müşterinin çerezini ezer — sepeti ve girişi uçar.
 *
 * CSRF muafiyet listesine yazmak YETMEZ: muaf yolda bile XSRF çerezi
 * eklenmeye çalışılıp 500 verebiliyor. Çözüm, oturum katmanının
 * tamamını bu yolların dışında tutmak.
 */

Route::post('paytr/callback', PayTrCallbackController::class)->name('paytr.callback');

/*
 * Dönüş adresleri İMZALI.
 *
 * Sipariş numarası tahmin edilebilir (ZEY-260920-0001 → -0002). İmza
 * olmasa sıradaki numarayı deneyen biri başkasının sipariş özetini,
 * adını ve tutarını görebilirdi. `signed` ara katmanı oturum gerektirmez,
 * o yüzden bu grupta güvenle kullanılabilir.
 */
Route::match(['get', 'post'], 'odeme/donus/{order:number}', [PaymentReturnController::class, 'ok'])
    ->middleware('signed')
    ->name('payment.return');

Route::match(['get', 'post'], 'odeme/hata/{order:number}', [PaymentReturnController::class, 'fail'])
    ->middleware('signed')
    ->name('payment.failed');
