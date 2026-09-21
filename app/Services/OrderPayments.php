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
    public function __construct(
        private readonly OrderStock $stock,
        private readonly Notifier $notifier,
    ) {}

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

        /*
         * GECİKMELİ ÖDEME: rezervi zeys:rezerv-temizle tarafından
         * bırakılmış (ya da ödemesi "başarısız" düşmüş) bir siparişe
         * sonradan "başarılı" bildirimi gelebilir. Para gelmiştir ama
         * stok artık rezerve DEĞİL.
         *
         * Düz devam edilseydi sipariş "ödendi" olur, commit rezerv
         * olmadığı için stoğu düşürmez ve aynı parça iki kişiye satılırdı.
         * Önce yeniden rezerve etmeyi deniyoruz; stok yetmezse sipariş
         * elle incelenmek üzere işaretleniyor.
         */
        if ($order->stock_state === 'none') {
            $eksik = $this->stock->reserve($order->fresh('items'));

            if ($eksik !== []) {
                $order->forceFill([
                    'payment_status' => 'paid',
                    'status' => 'cancelled',
                    'paid_at' => now(),
                    'payment_meta' => array_merge($order->payment_meta ?? [], $meta, ['gecikmeli_odeme' => true]),
                    'admin_note' => trim(($order->admin_note ?? '')
                        ."\nİPTAL EDİLMİŞ SİPARİŞE GECİKMELİ ÖDEME GELDİ, stok yetmiyor: "
                        .implode(', ', $eksik).'. Müşteriye iade ya da tedarik — elle incelenmeli.'),
                ])->save();

                Log::error('Gecikmeli ödeme: stok yok, elle incelenmeli', ['order' => $order->number]);

                return;
            }
        }

        $order->forceFill([
            'payment_status' => 'paid',
            'status' => 'paid',
            'paid_at' => now(),
            'payment_meta' => array_merge($order->payment_meta ?? [], $meta),
        ])->save();

        // Rezerv gerçek stok düşümüne dönüşür
        $this->stock->commit($order->fresh('items'));

        /*
         * Onay e-postası ödeme DOĞRULANDIKTAN sonra gider; sipariş
         * verildiğinde gitseydi ödemesi düşen siparişler için de
         * "siparişiniz alındı" gönderilmiş olurdu.
         *
         * Gönderim hatası burayı düşürmez (bkz. Notifier).
         */
        $this->notifier->orderPlaced($order->fresh('items'));
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
