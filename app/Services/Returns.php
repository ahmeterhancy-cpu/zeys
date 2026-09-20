<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * İade ve değişim akışı.
 *
 * Konfeksiyonda iade istisna değil kuraldır ("beden tutmadı"), bu yüzden
 * gerçek bir durum makinesi var:
 *
 *   opened ──▶ awaiting_shipment ──▶ received ──▶ approved ──▶ completed
 *      │                │                     └──▶ rejected
 *      └── cancelled ◀──┘
 *
 * Değişim iadeden farklıdır: para geri gitmez, yerine başka bir varyant
 * gönderilir.
 */
class Returns
{
    public function __construct(
        private readonly OrderStock $stock,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Cayma hakkı süresi dolmuş mu?
     *
     * 6502 sayılı kanun: teslim tarihinden itibaren 14 gün. Henüz teslim
     * edilmemiş siparişte süre başlamaz, iade her zaman açılabilir.
     */
    public function withdrawalDeadline(Order $order): ?Carbon
    {
        if (! $order->delivered_at) {
            return null;
        }

        return $order->delivered_at->copy()->addDays((int) config('shop.cayma_hakki_gun'));
    }

    public function isWithinWithdrawalPeriod(Order $order): bool
    {
        $deadline = $this->withdrawalDeadline($order);

        return $deadline === null || $deadline->isFuture();
    }

    /**
     * Bir sipariş kaleminden daha kaç adet iade edilebilir?
     *
     * Kapanmış (reddedilmiş/iptal) talepler sayılmaz; açık ve tamamlanmış
     * talepler sayılır — yoksa aynı parça iki kez iade edilebilir.
     */
    public function returnableQuantity(OrderItem $item): int
    {
        $used = (int) ReturnRequestItem::where('order_item_id', $item->id)
            ->whereHas('request', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))
            ->sum('quantity');

        return max(0, $item->quantity - $used);
    }

    /**
     * Talep aç.
     *
     * @param  array<int, array{quantity:int, exchange_variant_id?:int|null}>  $items
     *                                                                                 order_item_id => detay
     */
    public function open(Order $order, string $type, string $reason, array $items, ?string $note = null): ReturnRequest
    {
        if ($order->payment_status !== 'paid') {
            throw new RuntimeException('Ödemesi tamamlanmamış sipariş için iade talebi açılamaz.');
        }

        if (! $this->isWithinWithdrawalPeriod($order)) {
            throw new RuntimeException('Cayma hakkı süresi dolmuş.');
        }

        if ($items === []) {
            throw new RuntimeException('İade edilecek ürün seçilmedi.');
        }

        return DB::transaction(function () use ($order, $type, $reason, $items, $note) {
            $request = ReturnRequest::create([
                'number' => ReturnRequest::nextNumber(),
                'order_id' => $order->id,
                'type' => $type,
                'status' => 'opened',
                'reason' => $reason,
                'customer_note' => $note,
            ]);

            foreach ($items as $orderItemId => $detail) {
                $orderItem = $order->items()->whereKey($orderItemId)->first();

                if (! $orderItem) {
                    throw new RuntimeException('Sipariş kalemi bulunamadı.');
                }

                $quantity = (int) $detail['quantity'];
                $available = $this->returnableQuantity($orderItem);

                if ($quantity < 1 || $quantity > $available) {
                    throw new RuntimeException(
                        "{$orderItem->display_name} için en fazla {$available} adet iade edilebilir."
                    );
                }

                ReturnRequestItem::create([
                    'return_request_id' => $request->id,
                    'order_item_id' => $orderItem->id,
                    'quantity' => $quantity,
                    'exchange_variant_id' => $type === 'exchange'
                        ? ($detail['exchange_variant_id'] ?? null)
                        : null,
                ]);
            }

            return $request->fresh('items');
        });
    }

    /** Müşteriye kargo bilgisi verildi, ürünü geri göndermesi bekleniyor. */
    public function awaitShipment(ReturnRequest $request): void
    {
        $this->assertStatus($request, ['opened']);

        $request->update(['status' => 'awaiting_shipment']);
    }

    public function markShippedBack(ReturnRequest $request, ?string $carrier, ?string $tracking): void
    {
        $this->assertStatus($request, ['opened', 'awaiting_shipment']);

        $request->update([
            'status' => 'awaiting_shipment',
            'return_carrier' => $carrier,
            'return_tracking_number' => $tracking,
            'shipped_back_at' => now(),
        ]);
    }

    /** Ürün depoya ulaştı, incelenecek. */
    public function markReceived(ReturnRequest $request): void
    {
        $this->assertStatus($request, ['opened', 'awaiting_shipment']);

        $request->update(['status' => 'received', 'received_at' => now()]);
    }

    /**
     * Talep onaylandı.
     *
     * İadede: stok geri gelir, iade tutarı siparişe işlenir.
     * Değişimde: stok geri gelir ama para iadesi YOKTUR; yeni varyant
     * gönderilmek üzere rezerve edilir.
     */
    public function approve(ReturnRequest $request, ?string $adminNote = null): void
    {
        $this->assertStatus($request, ['received']);

        DB::transaction(function () use ($request, $adminNote) {
            $request->load('items.orderItem', 'order');

            // Geri gelen parçaların stoğu
            $geriGelen = [];
            $iadeTutari = 0.0;

            foreach ($request->items as $item) {
                if ($variantId = $item->orderItem->product_variant_id) {
                    $geriGelen[$variantId] = ($geriGelen[$variantId] ?? 0) + $item->quantity;
                }

                $iadeTutari += $item->refundValue();
            }

            $this->stock->restoreQuantities($geriGelen);

            if ($request->is_exchange) {
                $request->update([
                    'status' => 'approved',
                    'refund_amount' => 0,
                    'admin_note' => $adminNote,
                    'resolved_at' => now(),
                ]);

                /*
                 * Değişimde para geri gitmez; gidecek yeni varyant rezerve edilir.
                 *
                 * SIRA ÖNEMLİ: bu çağrı stok yetersizse admin_note'a uyarı
                 * ekliyor. Önce çağrılırsa yukarıdaki update uyarıyı null'la
                 * eziyor ve uyarı hiç görünmüyor.
                 */
                $this->reserveExchangeVariants($request->fresh('items'));

                $this->notifier->returnResolved($request->fresh());

                return;
            }

            $order = $request->order;

            $order->forceFill([
                'refunded_total' => round((float) $order->refunded_total + $iadeTutari, 2),
                'payment_status' => $this->refundStatusFor($order, $iadeTutari),
            ])->save();

            $request->update([
                'status' => 'approved',
                'refund_amount' => $iadeTutari,
                'admin_note' => $adminNote,
                'resolved_at' => now(),
            ]);

            $this->notifier->returnResolved($request->fresh());
        });
    }

    public function reject(ReturnRequest $request, string $adminNote): void
    {
        $this->assertStatus($request, ['received', 'opened', 'awaiting_shipment']);

        $request->update([
            'status' => 'rejected',
            'admin_note' => $adminNote,
            'resolved_at' => now(),
        ]);

        // Ret gerekcesi musteriye AYNEN iletilir
        $this->notifier->returnResolved($request->fresh());
    }

    /** İade parası gönderildi ya da değişim ürünü kargolandı. */
    public function complete(ReturnRequest $request, ?string $carrier = null, ?string $tracking = null): void
    {
        $this->assertStatus($request, ['approved']);

        $request->update([
            'status' => 'completed',
            'exchange_carrier' => $carrier,
            'exchange_tracking_number' => $tracking,
        ]);

        $this->notifier->returnResolved($request->fresh());
    }

    public function cancel(ReturnRequest $request): void
    {
        if (! $request->is_cancellable) {
            throw new RuntimeException('Bu aşamadaki talep iptal edilemez.');
        }

        $request->update(['status' => 'cancelled', 'resolved_at' => now()]);
    }

    /**
     * Değişimde gidecek varyantı rezerve et.
     *
     * Stok yoksa talep yine onaylanır ama not düşülür — müşterinin ürünü
     * zaten depoya geldi, geri çeviremeyiz.
     */
    private function reserveExchangeVariants(ReturnRequest $request): void
    {
        $eksik = [];

        foreach ($request->items as $item) {
            $variant = $item->exchangeVariant;

            if (! $variant) {
                continue;
            }

            if ($variant->available_stock < $item->quantity) {
                $eksik[] = $variant->sku;

                continue;
            }

            $variant->increment('reserved', $item->quantity);
        }

        if ($eksik !== []) {
            $request->forceFill([
                'admin_note' => trim(($request->admin_note ?? '')."\nDEĞİŞİM STOĞU YETERSİZ: ".implode(', ', $eksik)),
            ])->save();
        }
    }

    /** Tamamı iade edildiyse refunded, kısmen ise partially_refunded. */
    private function refundStatusFor(Order $order, float $yeniIade): string
    {
        $toplam = (float) $order->refunded_total + $yeniIade;

        return $toplam >= (float) $order->grand_total ? 'refunded' : 'partially_refunded';
    }

    /** @param  array<string>  $allowed */
    private function assertStatus(ReturnRequest $request, array $allowed): void
    {
        if (! in_array($request->status, $allowed, true)) {
            throw new RuntimeException(
                "Bu geçiş yapılamaz: talep '{$request->status_label}' durumunda."
            );
        }
    }
}
