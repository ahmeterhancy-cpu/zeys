<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\VariantMatrix;

/**
 * Ürünün min/max fiyat ve toplam stok önbelleğini varyant değiştikçe tazeler.
 *
 * Elle çağırmaya güvenilmiyor: panelden stok düzeltmesi, CSV içe aktarma ve
 * sipariş sonrası stok düşümü farklı yollardan geçiyor; biri unutulursa
 * vitrinde yanlış fiyat/stok görünür.
 */
class ProductVariantObserver
{
    /**
     * Toplu üretim sırasında her satırda yeniden hesaplamamak için
     * geçici olarak susturulur; VariantMatrix sonunda bir kez tazeler.
     */
    public static bool $muted = false;

    public function saved(ProductVariant $variant): void
    {
        $this->refresh($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->refresh($variant);
    }

    private function refresh(ProductVariant $variant): void
    {
        if (static::$muted) {
            return;
        }

        if ($product = $variant->product()->first()) {
            app(VariantMatrix::class)->refreshProduct($product);
        }
    }
}
