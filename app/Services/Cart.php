<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\ProductVariant;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Oturum tabanlı sepet.
 *
 * Oturumda YALNIZCA varyant kimliği ve adet durur; fiyat her okumada
 * veritabanından tazelenir, böylece istemci tarafından oynanamaz.
 *
 * Satır kimliği ürün değil VARYANTTIR — "Siyah M" ile "Siyah L" ayrı
 * satırdır ve ayrı stoğa bakar.
 */
class Cart
{
    private const KEY = 'cart';

    private const COUPON_KEY = 'cart_coupon';

    private const MAX_QTY = 20;

    public function __construct(private readonly Session $session) {}

    /** @return array<int, int> varyant kimliği => adet */
    public function raw(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function add(ProductVariant $variant, int $quantity = 1): void
    {
        $cart = $this->raw();
        $current = $cart[$variant->id] ?? 0;

        $cart[$variant->id] = $this->clamp($variant, $current + $quantity);

        $this->session->put(self::KEY, $cart);
    }

    public function update(ProductVariant $variant, int $quantity): void
    {
        $cart = $this->raw();

        if ($quantity <= 0) {
            unset($cart[$variant->id]);
        } else {
            $cart[$variant->id] = $this->clamp($variant, $quantity);
        }

        $this->session->put(self::KEY, $cart);
    }

    public function remove(int $variantId): void
    {
        $cart = $this->raw();
        unset($cart[$variantId]);

        $this->session->put(self::KEY, $cart);
    }

    public function clear(): void
    {
        $this->session->forget([self::KEY, self::COUPON_KEY]);
    }

    /**
     * Sepet satırları, güncel fiyat ve stok bilgisiyle.
     *
     * Aradan ürün pasife alınmış ya da silinmişse satır düşer — sepette
     * duran eski bir kimlik yüzünden kasada patlamasın.
     *
     * @return Collection<int, array>
     */
    public function lines(): Collection
    {
        $cart = $this->raw();

        if ($cart === []) {
            return collect();
        }

        $variants = ProductVariant::whereIn('id', array_keys($cart))
            ->where('is_active', true)
            ->with(['product', 'optionValues.option'])
            ->get()
            ->filter(fn (ProductVariant $v) => $v->product && $v->product->is_active);

        return $variants->map(function (ProductVariant $variant) use ($cart) {
            $requested = (int) $cart[$variant->id];
            $quantity = min($requested, max(0, $variant->available_stock));
            $price = (float) $variant->price;

            return [
                'variant' => $variant,
                'product' => $variant->product,
                'label' => $variant->label,
                'quantity' => $quantity,
                'requested' => $requested,
                // Stok yetmediği için adedi kırpıldıysa kasada uyarı gösterilir
                'adjusted' => $quantity !== $requested,
                // Tamamen tükenmiş satır sepette KALIR, adedi sıfırdır.
                // Sessizce silinirse müşteri o ürünü aldığını sanarken
                // sipariş onsuz geçer — en kötü sonuç bu.
                'sold_out' => $quantity === 0,
                'unit_price' => $price,
                'line_total' => round($price * $quantity, 2),
            ];
        })->values();
    }

    public function subtotal(): float
    {
        return round($this->lines()->sum('line_total'), 2);
    }

    public function count(): int
    {
        return (int) $this->lines()->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    /** Kasaya geçmeye engel olan satırlar: tükenmiş ya da adedi kırpılmış. */
    public function problemLines(): Collection
    {
        return $this->lines()->filter(fn (array $line) => $line['adjusted']);
    }

    // --- Kupon ---

    public function applyCoupon(string $code): bool
    {
        $coupon = Coupon::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();

        if (! $coupon || ! $coupon->isUsableFor($this->subtotal())) {
            return false;
        }

        $this->session->put(self::COUPON_KEY, $coupon->code);

        return true;
    }

    public function forgetCoupon(): void
    {
        $this->session->forget(self::COUPON_KEY);
    }

    public function coupon(): ?Coupon
    {
        $code = $this->session->get(self::COUPON_KEY);

        if (! $code) {
            return null;
        }

        $coupon = Coupon::where('code', $code)->first();

        // Sepet küçüldüyse kupon geçersizleşmiş olabilir
        return $coupon && $coupon->isUsableFor($this->subtotal()) ? $coupon : null;
    }

    public function discount(): float
    {
        return $this->coupon()?->discountFor($this->subtotal()) ?? 0.0;
    }

    // --- Kargo ve toplam ---

    /** İndirim SONRASI tutara bakılır — kupon eşiği düşürebilir. */
    public function shipping(): float
    {
        if ($this->isEmpty()) {
            return 0.0;
        }

        $esik = (float) config('shop.kargo.ucretsiz_esigi');
        $net = $this->subtotal() - $this->discount();

        return $net >= $esik ? 0.0 : (float) config('shop.kargo.ucret');
    }

    /** Ücretsiz kargoya ne kadar kaldı — vitrinde teşvik olarak gösterilir. */
    public function freeShippingRemaining(): float
    {
        $esik = (float) config('shop.kargo.ucretsiz_esigi');
        $net = $this->subtotal() - $this->discount();

        return max(0, round($esik - $net, 2));
    }

    public function total(): float
    {
        return round($this->subtotal() - $this->discount() + $this->shipping(), 2);
    }

    /** Stoğu aşan ya da sınırı geçen adetleri kırp. */
    private function clamp(ProductVariant $variant, int $quantity): int
    {
        return max(1, min($quantity, self::MAX_QTY, max(1, $variant->available_stock)));
    }
}
