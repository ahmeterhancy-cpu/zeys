<?php

namespace App\Services;

use App\Exceptions\StockShortage;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Observers\ProductVariantObserver;
use Illuminate\Support\Facades\DB;

/**
 * Varyant başına stok hareketleri.
 *
 * İki aşamalı: ödeme başlarken stok REZERVE edilir (satılabilir adetten
 * düşer ama gerçek stok durur), ödeme onaylanınca gerçek stoktan DÜŞÜLÜR.
 * Ödeme düşerse rezerv serbest bırakılır.
 *
 *   none ──reserve──▶ reserved ──commit──▶ committed
 *                        │                     │
 *                     release               restore
 *                        ▼                     ▼
 *                       none                  none
 *
 * `orders.stock_state` sayesinde aynı sipariş iki kez rezerve edilmez,
 * iki kez düşülmez, iki kez geri yüklenmez. PayTR callback'i tekrar
 * gönderebildiği için bu ŞART.
 */
class OrderStock
{
    /**
     * Ödeme öncesi rezervasyon.
     *
     * @return array<string> stoğu yetmeyen satırların etiketi (boşsa sorun yok)
     */
    public function reserve(Order $order): array
    {
        if ($order->stock_state !== 'none') {
            return [];
        }

        $short = [];

        /*
         * Hepsi ya da hiçbiri. Bir satır tutmazsa işlem geri sarılır ve
         * ÖNCEKİ satırların rezervi kendiliğinden geri alınır.
         *
         * Bunu elle telafi etmek denendi ve hatalıydı: hiç rezerve edilmemiş
         * satırların adedini de düşürüyor, yani araya giren BAŞKA bir
         * siparişin rezervini çalıyordu. Geri sarma hem doğru hem kısa.
         */
        try {
            DB::transaction(function () use ($order, &$short) {
                foreach ($order->items as $item) {
                    if (! $item->product_variant_id) {
                        continue;
                    }

                    $variant = ProductVariant::whereKey($item->product_variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $variant || $variant->available_stock < $item->quantity) {
                        $short[] = $item->display_name;

                        continue;
                    }

                    $variant->increment('reserved', $item->quantity);
                }

                if ($short !== []) {
                    throw new StockShortage;
                }

                $order->update(['stock_state' => 'reserved']);
            });
        } catch (StockShortage) {
            // Rezervler geri sarıldı; eksik satırların listesi $short'ta.
        }

        return $short;
    }

    /** Ödeme onaylandı: rezerv gerçek stok düşümüne dönüşür. */
    public function commit(Order $order): void
    {
        if ($order->stock_state !== 'reserved') {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }

                $variant = ProductVariant::whereKey($item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    continue;
                }

                $variant->forceFill([
                    'stock' => $variant->stock - $item->quantity,
                    // max(0): rezerv asla negatife düşmemeli. Negatif rezerv
                    // hayalet stok yaratır ve GERÇEK fazla satışa yol açar.
                    'reserved' => max(0, $variant->reserved - $item->quantity),
                ])->save();
            }

            $order->update(['stock_state' => 'committed']);
        });
    }

    /** Ödeme düştü / sepet zaman aşımına uğradı: rezervi serbest bırak. */
    public function release(Order $order): void
    {
        if ($order->stock_state !== 'reserved') {
            return;
        }

        $this->releaseReservations($order);

        $order->update(['stock_state' => 'none']);
    }

    /** İptal / iade: düşülmüş stoğu geri ekle. */
    public function restore(Order $order): void
    {
        if ($order->stock_state !== 'committed') {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }

                $variant = ProductVariant::whereKey($item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                $variant?->increment('stock', $item->quantity);
            }

            $order->update(['stock_state' => 'none']);
        });
    }

    /** Rezerv adetlerini geri ver. release() üzerinden çağrılır. */
    private function releaseReservations(Order $order): void
    {
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if (! $item->product_variant_id) {
                    continue;
                }

                $variant = ProductVariant::whereKey($item->product_variant_id)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    continue;
                }

                $variant->forceFill([
                    'reserved' => max(0, $variant->reserved - $item->quantity),
                ])->save();
            }
        });
    }

    /**
     * Tutarsızlık onarımı: bir varyantın rezervi, o varyantı bekleyen
     * açık siparişlerin toplamından fazla olamaz. Negatife düşmüş ya da
     * şişmiş rezervleri düzeltir.
     */
    public function reconcile(ProductVariant $variant): void
    {
        $beklenen = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_variant_id', $variant->id)
            ->where('orders.stock_state', 'reserved')
            ->sum('order_items.quantity');

        if ((int) $variant->reserved === $beklenen) {
            return;
        }

        ProductVariantObserver::$muted = true;

        try {
            $variant->forceFill(['reserved' => max(0, $beklenen)])->save();
        } finally {
            ProductVariantObserver::$muted = false;
        }

        app(VariantMatrix::class)->refreshProduct($variant->product);
    }
}
