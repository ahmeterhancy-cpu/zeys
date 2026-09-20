<?php

namespace App\Services;

use App\Mail\BackInStock;
use App\Models\ProductVariant;
use App\Models\StockInquiry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * "Stokta yok — haber ver".
 *
 * Kayıt ÜRÜNE değil VARYANTA bağlı: müşteri "Siyah M" bekliyor;
 * "Siyah L" geldiğinde haber vermek yanlış olur ve güven kaybettirir.
 *
 * Bildirim, varyantın stoğu artınca gönderilir. Gönderilen kayıt
 * silinmez, `notified_at` damgalanır — aynı kişiye aynı varyant için
 * ikinci kez posta gitmesin.
 */
class StockAlerts
{
    /** Bildirim isteğini kaydet. Zaten varsa tekrar oluşturmaz. */
    public function subscribe(ProductVariant $variant, string $email): StockInquiry
    {
        return StockInquiry::updateOrCreate(
            [
                'product_variant_id' => $variant->id,
                'email' => mb_strtolower(trim($email)),
            ],
            ['notified_at' => null],
        );
    }

    /**
     * Varyantın stoğu geldiyse bekleyenlere haber ver.
     *
     * @return int gönderilen bildirim sayısı
     */
    public function notifyIfBack(ProductVariant $variant): int
    {
        if ($variant->available_stock < 1) {
            return 0;
        }

        $bekleyenler = StockInquiry::where('product_variant_id', $variant->id)
            ->whereNull('notified_at')
            ->get();

        if ($bekleyenler->isEmpty()) {
            return 0;
        }

        $variant->loadMissing(['product', 'optionValues.option']);

        $gonderilen = 0;

        foreach ($bekleyenler as $istek) {
            try {
                Mail::to($istek->email)->send(new BackInStock($variant));

                $istek->update(['notified_at' => now()]);
                $gonderilen++;
            } catch (Throwable $e) {
                /*
                 * Gönderilemeyen kayıt damgalanmaz; bir sonraki stok
                 * hareketinde tekrar denenir. Hata burayı düşürmez —
                 * stok güncellemesi bu yüzden geri alınmamalı.
                 */
                Log::error('Stok bildirimi gönderilemedi: '.$e->getMessage(), [
                    'varyant' => $variant->sku,
                    'eposta' => $istek->email,
                ]);
            }
        }

        return $gonderilen;
    }
}
