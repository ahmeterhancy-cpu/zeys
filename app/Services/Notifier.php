<?php

namespace App\Services;

use App\Mail\DusukStok;
use App\Mail\OrderPlaced;
use App\Mail\OrderShipped;
use App\Mail\ReturnResolved;
use App\Mail\SozlesmeBelgeleri;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Müşteri bildirimleri.
 *
 * TASARIM KARARI: gönderim hatası ÇAĞIRANI DÜŞÜRMEZ.
 *
 * Bu sınıfın çağrıldığı yerler ödeme callback'i ve panel eylemleri.
 * PayTR gövdesi "OK" olmayan her yanıtı başarısız sayıp bildirimi
 * tekrar tekrar gönderiyor; SMTP bir saniye yanıt vermediği için
 * siparişin iki kez işlenmesi ya da sonsuz tekrar kabul edilemez.
 *
 * Bu yüzden her gönderim try/catch içinde ve hata yalnızca günlüğe
 * yazılıyor. Postanın gitmemesi kötü; siparişin bozulması daha kötü.
 */
class Notifier
{
    public function orderPlaced(Order $order): bool
    {
        $sonuc = $this->gonder(
            $order->customer_email,
            new OrderPlaced($order),
            ['tur' => 'siparis_alindi', 'siparis' => $order->number],
        );

        /*
         * Mağazaya da bir kopya. Ayrı çağrı, çünkü müşteriye gitmesi
         * başarısız olsa bile mağazanın siparişten haberi olmalı —
         * bcc kullanılsaydı tek hata ikisini birden düşürürdü.
         */
        if ($bildirim = config('shop.siparis_bildirim_epostasi')) {
            $this->gonder(
                $bildirim,
                new OrderPlaced($order),
                ['tur' => 'siparis_alindi_magaza', 'siparis' => $order->number],
            );
        }

        return $sonuc;
    }

    /**
     * Sözleşme belgelerini müşteriye ilet ve gönderim anını kaydet.
     * Mağazaya kopya gitmez — belge müşterinin kişisel kaydıdır.
     */
    public function contractDocuments(Order $order): bool
    {
        if (! $order->contract_version && ! $order->preinfo_version) {
            return false; // onaylanmış metin yok (eski / test siparişi)
        }

        $gitti = $this->gonder(
            $order->customer_email,
            new SozlesmeBelgeleri($order),
            ['tur' => 'sozlesme_belgeleri', 'siparis' => $order->number],
        );

        if ($gitti) {
            $order->forceFill(['contract_sent_at' => now()])->saveQuietly();
        }

        return $gitti;
    }

    public function orderShipped(Order $order): bool
    {
        return $this->gonder(
            $order->customer_email,
            new OrderShipped($order),
            ['tur' => 'siparis_kargoda', 'siparis' => $order->number],
        );
    }

    public function returnResolved(ReturnRequest $talep): bool
    {
        $talep->loadMissing(['order', 'items.orderItem', 'items.exchangeVariant']);

        $eposta = $talep->order?->customer_email;

        if (! $eposta) {
            return false;
        }

        return $this->gonder(
            $eposta,
            new ReturnResolved($talep),
            ['tur' => 'iade_sonucu', 'talep' => $talep->number],
        );
    }

    /**
     * Mağazaya: bir varyantın stoğu eşiğe indi ya da bitti.
     * Adres boşsa sessizce geçilir — bildirim isteğe bağlı.
     */
    public function lowStock(ProductVariant $variant): bool
    {
        $alici = config('shop.siparis_bildirim_epostasi');

        if (! $alici) {
            return false;
        }

        return $this->gonder(
            $alici,
            new DusukStok($variant),
            ['tur' => 'dusuk_stok', 'sku' => $variant->sku, 'stok' => $variant->stock],
        );
    }

    /** @param  array<string, mixed>  $baglam */
    private function gonder(?string $alici, Mailable $mesaj, array $baglam): bool
    {
        if (! $alici || ! filter_var($alici, FILTER_VALIDATE_EMAIL)) {
            Log::warning('E-posta gönderilemedi: geçersiz alıcı', $baglam);

            return false;
        }

        try {
            Mail::to($alici)->send($mesaj);

            return true;
        } catch (Throwable $e) {
            // Yutuluyor — bkz. sınıf açıklaması
            Log::error('E-posta gönderilemedi: '.$e->getMessage(), $baglam);

            return false;
        }
    }
}
