<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Yarıda kalan ödemelerin rezervini serbest bırakır.
 *
 * Müşteri PayTR sayfasını kapatıp giderse callback bazen HİÇ gelmez.
 * O siparişin rezervi sonsuza kadar stok tutar; beden vitrinde "tükendi"
 * görünür ama aslında rafta durur — sessiz satış kaybı.
 *
 * PayTR ödeme süresi (`paytr.timeout_limit`) 30 dakika. Bunun üstüne
 * pay bırakılıyor: geç gelen bir "başarılı" callback'i, rezervi
 * bırakılmış siparişe denk gelmesin.
 *
 * Geç gelen "başarılı" callback yine de gelirse OrderPayments::markPaid
 * stoğu yeniden rezerve etmeyi dener; stok kalmamışsa siparişi elle
 * incelenmek üzere işaretler. Bkz. OrderPayments::markPaid.
 */
class ReleaseStaleReservations extends Command
{
    protected $signature = 'zeys:rezerv-temizle {--dakika=90 : Bu kadar dakikadan eski bekleyen ödemeler}';

    protected $description = 'Ödemesi yarıda kalan siparişlerin stok rezervini serbest bırakır';

    public function handle(OrderStock $stock): int
    {
        $dakika = max(45, (int) $this->option('dakika'));

        $siparisler = Order::query()
            ->where('stock_state', 'reserved')
            ->where('payment_status', 'pending')
            ->where('created_at', '<', now()->subMinutes($dakika))
            ->with('items')
            ->get();

        foreach ($siparisler as $order) {
            $stock->release($order);

            $order->forceFill([
                'status' => 'cancelled',
                'admin_note' => trim(($order->admin_note ?? '')
                    ."\nÖdeme {$dakika} dk içinde tamamlanmadı; rezerv otomatik bırakıldı."),
            ])->save();

            Log::info('Takılı rezerv bırakıldı', ['siparis' => $order->number]);
        }

        $this->info($siparisler->count().' siparişin rezervi bırakıldı.');

        return self::SUCCESS;
    }
}
