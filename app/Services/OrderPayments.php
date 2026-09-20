<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Ödeme sonucunun siparişe işlenmesi.
 *
 * Her geçiş kendini tekrarlamaya karşı korumalıdır: PayTR aynı bildirimi
 * "OK" alana kadar tekrar tekrar gönderir, ayrıca ağ sorunlarında birden
 * fazla kez de gelebilir.
 */
class OrderPayments
{
    public function __construct(private readonly OrderStock $stock) {}

    /**
     * Ödeme onaylandı.
     *
     * @param  array<string, mixed>  $meta
     */
    public function markPaid(Order $order, array $meta = []): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $order->forceFill([
            'payment_status' => 'paid',
            'status' => 'paid',
            'paid_at' => now(),
            'payment_meta' => array_merge($order->payment_meta ?? [], $meta),
        ])->save();

        // Rezerv gerçek stok düşümüne dönüşür
        $this->stock->commit($order->fresh('items'));
    }

    /** Ödeme başarısız: rezerv serbest bırakılır, stok geri gelir. */
    public function markFailed(Order $order, ?string $reason = null): void
    {
        if ($order->payment_status === 'paid') {
            // Ödenmiş siparişi başarısız yapmayız; geç gelen bir bildirim olabilir.
            Log::warning('Ödenmiş siparişe başarısız bildirimi geldi', ['order' => $order->number]);

            return;
        }

        if ($order->payment_status === 'failed') {
            return;
        }

        $this->stock->release($order->fresh('items'));

        $order->forceFill([
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'payment_meta' => array_merge($order->payment_meta ?? [], [
                'failed_reason' => $reason,
                'failed_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    /**
     * Tutar uyuşmazlığı: beklenenden AZ para geldi.
     *
     * Sipariş "ödendi" YAPILMAZ. Donör projede bu durumda hata loglanıp
     * sipariş yine de ödendi işaretleniyordu — bu, eksik tahsilatla ürün
     * göndermek demek. Burada sipariş incelemeye düşer.
     *
     * Fazla gelen tutar sorun değil: taksit komisyonu müşteriye
     * yansıtıldığında PayTR beklenenden büyük bir total_amount bildirir.
     */
    public function flagAmountMismatch(Order $order, int $expected, int $received): void
    {
        Log::error('PayTR tutar uyuşmazlığı', [
            'order' => $order->number,
            'expected_kurus' => $expected,
            'received_kurus' => $received,
        ]);

        $order->forceFill([
            'status' => 'pending',
            'payment_status' => 'pending',
            'admin_note' => trim(($order->admin_note ?? '')."\nTUTAR UYUŞMAZLIĞI: beklenen {$expected} krş, gelen {$received} krş. Elle incelenmeli."),
            'payment_meta' => array_merge($order->payment_meta ?? [], [
                'amount_mismatch' => ['expected' => $expected, 'received' => $received, 'at' => now()->toIso8601String()],
            ]),
        ])->save();
    }
}
