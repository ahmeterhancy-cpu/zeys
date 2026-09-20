<?php

namespace App\Services;

use App\Models\Order;
use RuntimeException;

/**
 * Sipariş kargo geçişleri.
 *
 * Kargo ücreti sipariş anında hesaplanıp `shipping_total`a yazılır; burada
 * yeniden hesaplanmaz — kargo ayarı sonradan değişse de müşterinin ödediği
 * tutar sabit kalmalı.
 */
class OrderShipping
{
    public function __construct(private readonly Notifier $notifier) {}

    /** Kargoya verildi. */
    public function markShipped(Order $order, ?string $carrier, ?string $tracking): void
    {
        if ($order->payment_status !== 'paid') {
            throw new RuntimeException('Ödemesi tamamlanmamış sipariş kargoya verilemez.');
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw new RuntimeException('İptal edilmiş sipariş kargoya verilemez.');
        }

        $order->forceFill([
            'status' => 'shipped',
            'shipping_carrier' => $carrier ?: config('shop.kargo.firma'),
            'tracking_number' => $tracking,
            'shipped_at' => now(),
        ])->save();

        $this->notifier->orderShipped($order->fresh('items'));
    }

    /**
     * Teslim edildi.
     *
     * Cayma hakkı süresi BURADAN başlar (6502 sayılı kanun: teslim
     * tarihinden itibaren 14 gün), o yüzden tarih doğru işaretlenmeli.
     */
    public function markDelivered(Order $order): void
    {
        if (! $order->shipped_at) {
            throw new RuntimeException('Kargoya verilmemiş sipariş teslim edilmiş sayılamaz.');
        }

        $order->forceFill([
            'status' => 'delivered',
            'delivered_at' => now(),
        ])->save();
    }

    /** Sepet tutarına göre kargo bedeli — kasada ve vitrinde aynı kural. */
    public function feeFor(float $netTotal): float
    {
        $esik = (float) config('shop.kargo.ucretsiz_esigi');

        return $netTotal >= $esik ? 0.0 : (float) config('shop.kargo.ucret');
    }
}
