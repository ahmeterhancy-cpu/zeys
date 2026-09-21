<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Cart;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Panelden girilen magaza ayarlari config('shop.*') uzerine
         * bindiriliyor. Uygulamanin geri kalani yalnizca config() okur.
         * Bkz. App\Models\Setting
         */
        Setting::configeBindir();

        /*
         * Sepet adedi basligin her sayfada gorunuyor.
         *
         * YALNIZCA vitrin sablonuna baglanir: PayTR donus sayfalari oturumsuz
         * calisiyor (bkz. routes/paytr.php) ve Cart oturuma bakiyor; global
         * baglansaydi o yollarda patlardi.
         */
        View::composer('layout.vitrin', function ($view) {
            $view->with('sepetAdedi', app(Cart::class)->count());
        });
    }
}
