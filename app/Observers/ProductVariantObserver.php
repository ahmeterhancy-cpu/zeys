<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\StockAlerts;
use App\Services\VariantMatrix;

/**
 * Ürünün min/max fiyat ve toplam stok önbelleğini varyant değiştikçe
 * tazeler; ayrıca stok geri geldiğinde bekleyenlere haber verir.
 *
 * Elle çağırmaya güvenilmiyor: panelden stok düzeltmesi, CSV içe
 * aktarma, iade onayı ve sipariş iptali farklı yollardan geçiyor;
 * biri unutulursa vitrinde yanlış stok görünür.
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
        if (static::$muted) {
            return;
        }

        $this->refresh($variant);
        $this->stokGeldiMi($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        if (static::$muted) {
            return;
        }

        $this->refresh($variant);
    }

    private function refresh(ProductVariant $variant): void
    {
        if ($product = $variant->product()->first()) {
            app(VariantMatrix::class)->refreshProduct($product);
        }
    }

    /**
     * Satılabilir stok SIFIRDAN yukarı çıktıysa bekleyenlere haber ver.
     *
     * Geçişe bakılıyor, anlık değere değil: yoksa stoğu zaten olan bir
     * varyantın her kaydedilişinde (fiyat düzeltmesi, etiket değişimi)
     * tekrar tekrar posta giderdi.
     */
    private function stokGeldiMi(ProductVariant $variant): void
    {
        if (! $variant->wasChanged(['stock', 'reserved'])) {
            return;
        }

        $oncekiStok = (int) $variant->getOriginal('stock');
        $oncekiRezerv = (int) $variant->getOriginal('reserved');
        $oncekiSatilabilir = max(0, $oncekiStok - $oncekiRezerv);

        if ($oncekiSatilabilir > 0 || $variant->available_stock < 1) {
            return;
        }

        app(StockAlerts::class)->notifyIfBack($variant);
    }
}
