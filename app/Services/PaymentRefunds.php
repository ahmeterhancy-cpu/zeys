<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\Payments\PayTrGateway;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Paranın ödeme kuruluşu üzerinden gerçekten iade edilmesi.
 *
 * Önceden iade onayı yalnızca TUTARI kaydediyordu; müşteriye "kartınıza
 * iade edilecek" e-postası gidiyor ama parayı birinin PayTR panelinden
 * elle göndermesi gerekiyordu. Unutulursa hem müşteri hem yasal sorun.
 *
 * ÇİFT İADE KİLİDİ: satır lockForUpdate ile kilitlenip damga yeniden
 * okunuyor; çift tıklama ya da iki yöneticinin aynı anda basması ikinci
 * isteği reddeder. Ağ çağrısı kilit içinde yapılıyor — düşük hacimde
 * kabul edilebilir, çift iadeden çok daha ucuz.
 */
class PaymentRefunds
{
    public function __construct(
        private readonly PayTrGateway $paytr,
        private readonly Returns $returns,
    ) {}

    public function kullanilabilir(): bool
    {
        return $this->paytr->isConfigured();
    }

    /** Onaylanmış iade talebinin parasını gönder, talebi tamamla. */
    public function refundReturn(ReturnRequest $talep): void
    {
        DB::transaction(function () use ($talep) {
            $kilitli = ReturnRequest::whereKey($talep->id)->lockForUpdate()->firstOrFail();

            if ($kilitli->payment_refunded_at) {
                throw new RuntimeException('Bu talebin parası zaten iade edildi.');
            }

            if ($kilitli->status !== 'approved') {
                throw new RuntimeException('Yalnızca onaylanmış talebin parası iade edilebilir.');
            }

            if ($kilitli->is_exchange) {
                throw new RuntimeException('Değişimde para iadesi yapılmaz.');
            }

            $order = $kilitli->order;
            $sonuc = $this->paytr->refund($order, (float) $kilitli->refund_amount);

            if (! $sonuc['ok']) {
                throw new RuntimeException('PayTR iadesi başarısız: '.$sonuc['error']);
            }

            $kilitli->forceFill(['payment_refunded_at' => now()])->save();
            $this->kaydet($order, (float) $kilitli->refund_amount, 'iade_talebi', $kilitli->number);
        });

        // Durumu "tamamlandı" yapar ve müşteriye "iade tutarınız gönderildi" gider
        $this->returns->complete($talep->fresh());
    }

    /**
     * Ödenmiş ama iptal edilen siparişin tamamını iade et.
     * Kısmi iade yapılmış siparişte kullanılmaz — tutar karışır.
     */
    public function refundOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $kilitli = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($kilitli->payment_status !== 'paid' || (float) $kilitli->refunded_total > 0) {
                throw new RuntimeException('Yalnızca hiç iade yapılmamış ödenmiş sipariş tamamen iade edilebilir.');
            }

            $sonuc = $this->paytr->refund($kilitli, (float) $kilitli->grand_total);

            if (! $sonuc['ok']) {
                throw new RuntimeException('PayTR iadesi başarısız: '.$sonuc['error']);
            }

            $kilitli->forceFill([
                'refunded_total' => $kilitli->grand_total,
                'payment_status' => 'refunded',
            ])->save();

            $this->kaydet($kilitli, (float) $kilitli->grand_total, 'siparis_iptali', $kilitli->number);
        });
    }

    /** İade geçmişi siparişin ödeme kaydına eklenir (muhasebe izi). */
    private function kaydet(Order $order, float $tutar, string $kaynak, string $referans): void
    {
        $meta = $order->fresh()->payment_meta ?? [];
        $meta['iade_kayitlari'][] = [
            'tutar' => number_format($tutar, 2, '.', ''),
            'kaynak' => $kaynak,
            'referans' => $referans,
            'yonetici' => auth()->user()?->email,
            'zaman' => now()->toIso8601String(),
        ];

        $order->forceFill(['payment_meta' => $meta])->saveQuietly();
    }
}
