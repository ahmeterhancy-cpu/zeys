<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(config('shop.ad'))
            ->brandLogo(asset('img/zeys-logo.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('img/zeys-logo-sm.png'))
            ->colors([
                /*
                 * TUZAK: Color::hex() KULLANMAYIN. Verilen renkten yalnızca
                 * hue'yu alıp kendi doygunluk/parlaklık rampasını kurar ve
                 * markanın altınını bambaşka bir sarıya çevirir.
                 *
                 * Aşağıdaki merdiven logodan ölçülen #BC9C51 çevresinde elle
                 * yazıldı. 800 ve üstü metin tonlarıdır (beyaz üstünde
                 * okunaklı), 500 ve altı yüzey/çizgi tonlarıdır.
                 */
                'primary' => [
                    50 => '250, 246, 234',
                    100 => '244, 236, 214',
                    200 => '236, 223, 183',
                    300 => '221, 201, 143',
                    400 => '205, 178, 109',
                    500 => '188, 156, 81',
                    600 => '161, 131, 63',
                    700 => '134, 106, 51',
                    800 => '110, 90, 51',
                    900 => '90, 74, 44',
                    950 => '51, 42, 24',
                ],
            ])
            ->navigationGroups([
                'Katalog',
                'Satış',
                'Ayarlar',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
