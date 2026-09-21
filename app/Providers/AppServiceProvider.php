<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Setting;
use App\Services\Cart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

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
            $view->with('menuKategorileri', $this->menuKategorileri());
        });

        // Mağaza sayfalarının yan sütunu: aynı kategori ağacı + koleksiyonlar
        View::composer('vitrin.parca.katalog', function ($view) {
            $view->with('menuKategorileri', $this->menuKategorileri());
            $view->with('menuKoleksiyonlari', $this->istekBasina('menuKoleksiyonlari', fn () => Collection::query()
                ->where('is_active', true)
                ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
                ->orderBy('position')
                ->get()));
        });

        Paginator::defaultView('vendor.pagination.zeys');
        Paginator::defaultSimpleView('vendor.pagination.zeys');
    }

    /**
     * Başlıktaki "Kategoriler" menüsü, arama kutusunun kategori seçimi ve
     * mağaza yan sütunu. Yalnız üst seviye + doğrudan alt kategoriler.
     * İstek başına bir kez sorgulanır. Tablo henüz yoksa (ilk migrate) boş.
     */
    private function menuKategorileri(): \Illuminate\Support\Collection
    {
        return $this->istekBasina('menuKategorileri', fn () => Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('position')
            ->get());
    }

    /**
     * İstek içinde tek sorgu. once() KULLANILMADI: süreç boyunca saklar —
     * testte aynı uygulama birden çok istek görür ve ikinci istek eski
     * menüyü alırdı.
     */
    private function istekBasina(string $anahtar, \Closure $uret): \Illuminate\Support\Collection
    {
        $istek = request();

        if (! $istek->attributes->has($anahtar)) {
            try {
                $istek->attributes->set($anahtar, $uret());
            } catch (Throwable) {
                return collect();
            }
        }

        return $istek->attributes->get($anahtar);
    }
}
