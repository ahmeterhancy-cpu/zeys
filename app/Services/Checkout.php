<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sepeti siparişe çevirir.
 *
 * Tutarların TAMAMI burada, sunucuda hesaplanır. İstemciden gelen hiçbir
 * fiyat/toplam değeri kullanılmaz.
 */
class Checkout
{
    public function __construct(
        private readonly Cart $cart,
        private readonly OrderStock $stock,
    ) {}

    /**
     * @param  array{name:string,email:string,phone:string}  $customer
     * @param  array  $shipping  Adres anlık görüntüsü
     * @param  array|null  $billing  Boşsa teslimat adresi kullanılır
     *
     * @throws RuntimeException sepet boşsa ya da stok yetmiyorsa
     */
    public function place(
        array $customer,
        array $shipping,
        ?array $billing = null,
        ?int $userId = null,
        ?string $note = null,
        ?string $contractVersion = null,
        ?string $ip = null,
    ): Order {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            throw new RuntimeException('Sepet boş.');
        }

        // Stok yetmediği için adedi kırpılan satır varsa müşteri onaylamadan
        // devam edilmez — sessizce az ürün göndermek en kötüsü.
        if ($lines->contains('adjusted', true)) {
            throw new RuntimeException('Sepetteki bazı ürünlerin stoğu değişti, lütfen sepeti gözden geçirin.');
        }

        $subtotal = $this->cart->subtotal();
        $discount = $this->cart->discount();
        $shippingTotal = $this->cart->shipping();
        $coupon = $this->cart->coupon();

        $order = DB::transaction(function () use (
            $lines, $customer, $shipping, $billing, $userId, $note,
            $subtotal, $discount, $shippingTotal, $coupon, $contractVersion, $ip
        ) {
            $order = Order::create([
                'number' => Order::nextNumber(),
                'user_id' => $userId,
                'status' => 'pending',
                'payment_status' => 'pending',
                'stock_state' => 'none',
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'shipping_address' => $shipping,
                'billing_address' => $billing,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shippingTotal,
                'grand_total' => round($subtotal - $discount + $shippingTotal, 2),
                'coupon_code' => $coupon?->code,
                'customer_note' => $note,
                'contract_version' => $contractVersion,
                'contract_accepted_at' => $contractVersion ? now() : null,
                'contract_ip' => $ip,
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']->id,
                    // Anlık görüntü: ürün sonradan silinse de satır okunabilir kalır
                    'name' => $line['product']->name,
                    'variant_label' => $line['label'],
                    'sku' => $line['variant']->sku,
                    'image' => $line['product']->hero_image,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                ]);
            }

            return $order;
        });

        $short = $this->stock->reserve($order->fresh('items'));

        if ($short !== []) {
            // Rezervasyon tutmadı: sipariş boşta kalmasın
            $order->update(['status' => 'cancelled', 'admin_note' => 'Stok yetersiz: '.implode(', ', $short)]);

            throw new RuntimeException('Stok yetersiz: '.implode(', ', $short));
        }

        if ($coupon) {
            $coupon->increment('used_count');
        }

        return $order->fresh('items');
    }
}
