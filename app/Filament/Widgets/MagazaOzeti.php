<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\ReturnRequests\ReturnRequestResource;
use App\Filament\Resources\StockInquiries\StockInquiryResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\StockInquiry;
use App\Support\Yetki;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Pano özeti — "bugün ne yapmam gerekiyor?"
 *
 * Süs metrikleri yerine İŞ bekleyen kalemler öne alındı: kargolanacak
 * sipariş, sonuçlanmamış iade, stoğu biten ürün. Her kart ilgili listeye
 * gidiyor.
 */
class MagazaOzeti extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $bugun = now()->startOfDay();

        $bugunkuSiparis = Order::where('payment_status', 'paid')->where('paid_at', '>=', $bugun);
        $bugunAdet = (clone $bugunkuSiparis)->count();
        $bugunCiro = (float) (clone $bugunkuSiparis)->sum('grand_total');

        $kargolanacak = Order::where('payment_status', 'paid')
            ->whereIn('status', ['paid', 'preparing'])
            ->count();

        $acikIade = ReturnRequest::whereNotIn('status', ['completed', 'rejected', 'cancelled'])->count();

        $bitenUrun = Product::where('is_active', true)->where('total_stock', '<', 1)->count();

        $stokTalebi = StockInquiry::whereNull('notified_at')->count();

        /*
         * 2 saatten eski ve hâlâ "rezerve" siparişler: ödemesi yarıda
         * kalmış, stoğu tutuyor. Sıfırdan büyükse bedenler boşuna
         * "tükendi" görünüyor olabilir.
         */
        $takiliRezerv = Order::where('stock_state', 'reserved')
            ->where('created_at', '<', now()->subHours(2))
            ->count();

        return [
            Stat::make('Bugün', $bugunAdet.' sipariş')
                // Ciro yalnız yöneticiye (bkz. App\Support\Yetki)
                ->description(Yetki::yonetici() ? number_format($bugunCiro, 2, ',', '.').' TL ciro' : 'Ödemesi alınan sipariş')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),

            Stat::make('Kargolanacak', (string) $kargolanacak)
                ->description($kargolanacak > 0 ? 'Ödemesi alınmış, kargo bekliyor' : 'Bekleyen yok')
                ->color($kargolanacak > 0 ? 'warning' : 'success')
                ->url(OrderResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'paid']]])),

            Stat::make('Açık iade / değişim', (string) $acikIade)
                ->description($acikIade > 0 ? 'Sonuçlanmamış talep' : 'Bekleyen yok')
                ->color($acikIade > 0 ? 'warning' : 'success')
                ->url(ReturnRequestResource::getUrl('index')),

            Stat::make('Stoğu biten ürün', (string) $bitenUrun)
                ->description('Satışta ama satılabilir adedi yok')
                ->color($bitenUrun > 0 ? 'danger' : 'success')
                ->url(ProductResource::getUrl('index')),

            Stat::make('Beden bekleyen müşteri', (string) $stokTalebi)
                ->description('"Haber ver" kaydı')
                ->color($stokTalebi > 0 ? 'info' : 'gray')
                ->url(StockInquiryResource::getUrl('index')),

            Stat::make('Takılı rezerv', (string) $takiliRezerv)
                ->description($takiliRezerv > 0 ? 'Ödemesi yarıda kalan sipariş stok tutuyor' : 'Sorun yok')
                ->color($takiliRezerv > 0 ? 'danger' : 'success')
                ->url(OrderResource::getUrl('index', ['tableFilters' => ['takili_rezerv' => ['isActive' => true]]])),
        ];
    }
}
