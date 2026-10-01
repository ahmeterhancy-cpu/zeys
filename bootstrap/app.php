<?php

use App\Http\Middleware\BakimPerdesi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            /*
             * PayTR uçları oturumsuz. `web` grubuna alınmazlar: StartSession,
             * iFrame içinden gelen POST'ta müşterinin oturum çerezini boş bir
             * oturumla eziyor. Ayrıntı: routes/paytr.php
             */
            /*
             * TUZAK (canlida gorundu): bos middleware listesi
             * SubstituteBindings'i de dusuruyordu; {order:number} HIC
             * cozulmuyor, denetleyiciye BOS bir Order geliyordu. Musteri
             * odeme sonrasi donus sayfasinda numarasiz "odemeniz isleniyor"
             * goruyordu — odeme basarili olsa bile, kalici olarak.
             */
            Route::middleware([SubstituteBindings::class])->group(base_path('routes/paytr.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Bakım perdesi — yalnız `web` grubu; PayTR uçları (routes/paytr.php)
        // bu grubun dışında olduğu için perde açıkken de ödeme işlenir.
        $middleware->appendToGroup('web', BakimPerdesi::class);

        // Oturum katmanı PayTR yollarına zaten uygulanmıyor; yine de CSRF
        // listesinden düşürülür ki ileride biri bu rotaları `web` grubuna
        // taşırsa istek sessizce 419'a düşmesin.
        $middleware->validateCsrfTokens(except: [
            'paytr/callback',
            'odeme/donus/*',
            'odeme/hata/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
