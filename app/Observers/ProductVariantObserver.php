<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\Notifier;
use App\Services\StockAlerts;
use App\Services\VariantMatrix;
use Illuminate\Support\Facades\DB;

/**
 * Ürünün min/max fiyat ve toplam stok önbelleğini varyant değiştikçe
 * tazeler; stok geri geldiğinde bekleyenlere, eşiğe indiğinde mağazaya
 * haber verir.
 *
 * Elle çağırmaya güvenilmiyor: panelden stok düzeltmesi, CSV içe
 * aktarma, iade onayı ve sipariş iptali farklı yollardan geçiyor;
 * biri unutulursa vitrinde yanlış stok görünür.
 *
 * NEDEN `saved` DEĞİL `created` + `updated`: stok ve rezerv çoğu yerde
 * `increment()`/`decrement()` ile değişiyor (rezerv, iptalde stok iadesi,
 * kısmi iade). Eloquent bunlarda YALNIZ `updated` olayını atar, `saved`ı
 * atmaz. Önceden her şey `saved`'daydı: iptal edilen siparişin stoğu
 * geri geldiğinde ürün önbelleği tazelenmiyor (vitrinde "tükendi"
 * kalıyordu) ve "stokta" postası hiç gitmiyordu.
 */
class ProductVariantObserver
{
    /**
     * Toplu üretim sırasında her satırda yeniden hesaplamamak için
     * geçici olarak susturulur; VariantMatrix sonunda bir kez tazeler.
     */
    public static bool $muted = false;

    public function created(ProductVariant $variant): void
    {
        if (static::$muted) {
            return;
        }

        $this->refresh($variant);
    }

    public function updated(ProductVariant $variant): void
    {
        if (static::$muted) {
            return;
        }

        $this->refresh($variant);
        $this->stokGeldiMi($variant);
        $this->stokAzaldiMi($variant);
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

    /**
     * Mağazaya düşük stok / tükendi uyarısı.
     *
     * Raftaki (fiziksel) stoğa bakılıyor, satılabilir stoğa değil:
     * satılabilir stok ödeme sayfası açılır açılmaz (rezervle) düşer,
     * yarıda bırakılan her ödeme sahte bir uyarı doğururdu. Panel de
     * sarı/kırmızı rozeti raftaki stoğa göre veriyor.
     *
     * Yalnız eşiğin ALTINA GEÇİŞTE bir kez; eşiğin altındayken her
     * satışta yeniden gönderilmez. Sıfıra inmek ayrıca bildirilir.
     */
    private function stokAzaldiMi(ProductVariant $variant): void
    {
        if (! $variant->wasChanged('stock')) {
            return;
        }

        $esik = (int) config('shop.dusuk_stok_esigi');
        $onceki = (int) $variant->getOriginal('stock');
        $simdi = (int) $variant->stock;

        $esigeIndi = $onceki > $esik && $simdi <= $esik;
        $tukendi = $onceki > 0 && $simdi < 1;

        if (! $esigeIndi && ! $tukendi) {
            return;
        }

        if (! $variant->is_active || ! $variant->product()->where('is_active', true)->exists()) {
            return;
        }

        // Ödeme kaydını yazan işlem geri alınırsa posta da gitmesin
        $id = $variant->getKey();
        DB::afterCommit(function () use ($id) {
            if ($guncel = ProductVariant::with(['product', 'optionValues.option'])->find($id)) {
                app(Notifier::class)->lowStock($guncel);
            }
        });
    }
}
