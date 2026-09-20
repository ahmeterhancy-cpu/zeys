<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PayTR iFrame API.
 *
 * Kart bilgisi YALNIZCA PayTR'nin sayfasına girilir; bizim sunucumuzdan
 * hiç geçmez. Bu, PCI-DSS yükümlülüğünü en aza indiren tek doğru yol.
 *
 * ÖNEMLİ: Tarayıcının dönüş adresine (merchant_ok_url) düşmesi ödemenin
 * başarılı olduğu anlamına GELMEZ. Siparişi "ödendi" yapan tek şey,
 * imzası doğrulanmış sunucu-sunucu bildirimi (callback).
 */
class PayTrGateway
{
    public function isConfigured(): bool
    {
        return config('paytr.merchant_id') !== ''
            && config('paytr.merchant_key') !== ''
            && config('paytr.merchant_salt') !== '';
    }

    /**
     * Ödeme için token alır.
     *
     * @return array{ok: bool, token: string|null, error: string|null}
     */
    public function createToken(Order $order, string $okUrl, string $failUrl): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'token' => null, 'error' => 'PayTR kimlik bilgileri tanımlı değil.'];
        }

        $merchantOid = $this->merchantOid($order);
        $amount = $this->kurus((float) $order->grand_total);
        $basket = $this->basket($order);
        $userIp = $this->clientIp();

        $payload = [
            'merchant_id' => config('paytr.merchant_id'),
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $order->customer_email,
            'payment_amount' => $amount,
            'paytr_token' => $this->tokenHash($merchantOid, $userIp, $order->customer_email, $amount, $basket),
            'user_basket' => $basket,
            'debug_on' => app()->isLocal() ? 1 : 0,
            'no_installment' => (int) config('paytr.no_installment'),
            'max_installment' => (int) config('paytr.max_installment'),
            'user_name' => $order->customer_name,
            'user_address' => $this->addressLine($order),
            'user_phone' => $order->customer_phone,
            'merchant_ok_url' => $okUrl,
            'merchant_fail_url' => $failUrl,
            'timeout_limit' => (int) config('paytr.timeout_limit'),
            'currency' => config('paytr.currency'),
            'test_mode' => (int) config('paytr.test_mode'),
            'lang' => config('paytr.lang'),
        ];

        try {
            $response = $this->http()->asForm()->post(config('paytr.token_endpoint'), $payload);
            $body = $response->json();

            if (($body['status'] ?? '') === 'success') {
                $order->forceFill([
                    'payment_provider' => 'paytr',
                    'payment_ref' => $merchantOid,
                    'payment_meta' => array_merge($order->payment_meta ?? [], [
                        'token_requested_at' => now()->toIso8601String(),
                        'requested_amount' => $amount,
                    ]),
                ])->save();

                return ['ok' => true, 'token' => $body['token'], 'error' => null];
            }

            $reason = $body['reason'] ?? $response->body();
            $reason = is_string($reason) ? $reason : 'Bilinmeyen hata';

            Log::warning('PayTR token alınamadı', ['order' => $order->number, 'reason' => $reason]);

            return ['ok' => false, 'token' => null, 'error' => $reason];
        } catch (Throwable $e) {
            Log::error('PayTR isteği başarısız: '.$e->getMessage(), ['order' => $order->number]);

            return ['ok' => false, 'token' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Bildirim imzasını doğrular.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        $key = config('paytr.merchant_key');
        $salt = config('paytr.merchant_salt');

        if ($key === '' || $salt === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac(
            'sha256',
            ($payload['merchant_oid'] ?? '').$salt.($payload['status'] ?? '').($payload['total_amount'] ?? ''),
            $key,
            true
        ));

        // hash_equals: zamanlama saldırısına karşı
        return hash_equals($expected, (string) ($payload['hash'] ?? ''));
    }

    public function iframeUrl(string $token): string
    {
        return rtrim(config('paytr.iframe_url'), '/').'/'.$token;
    }

    /** Callback'te gelen merchant_oid'den siparişi bulur. */
    public function resolveOrder(string $merchantOid): ?Order
    {
        if ($order = Order::where('payment_ref', $merchantOid)->first()) {
            return $order;
        }

        // Yedek: sondaki X<id> parçasından çöz.
        if (preg_match('/X(\d+)$/', $merchantOid, $m)) {
            return Order::find((int) $m[1]);
        }

        return null;
    }

    public function kurus(float $tl): int
    {
        return (int) round($tl * 100);
    }

    private function tokenHash(string $oid, string $ip, string $email, int $amount, string $basket): string
    {
        $hashStr = config('paytr.merchant_id').$ip.$oid.$email.$amount.$basket
            .(int) config('paytr.no_installment')
            .(int) config('paytr.max_installment')
            .config('paytr.currency')
            .(int) config('paytr.test_mode');

        return base64_encode(hash_hmac(
            'sha256',
            $hashStr.config('paytr.merchant_salt'),
            config('paytr.merchant_key'),
            true
        ));
    }

    /** Ödeme ucu — sertifika doğrulaması varsayılan olarak AÇIK. */
    private function http(): PendingRequest
    {
        $request = Http::timeout(25);

        if ($bundle = config('paytr.ca_bundle')) {
            return $request->withOptions(['verify' => $bundle]);
        }

        return config('paytr.verify_tls') ? $request : $request->withoutVerifying();
    }

    /** PayTR yalnızca harf/rakam kabul eder. */
    private function merchantOid(Order $order): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $order->number).'X'.$order->id;
    }

    private function clientIp(): string
    {
        $ip = request()->ip();

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    /** Sepet listesi: [[ad, birim fiyat, adet], ...] base64 JSON. */
    private function basket(Order $order): string
    {
        $items = $order->items->map(fn ($item) => [
            mb_substr($item->display_name, 0, 80),
            number_format((float) $item->unit_price, 2, '.', ''),
            (int) $item->quantity,
        ])->values()->all();

        if ((float) $order->shipping_total > 0) {
            $items[] = ['Kargo', number_format((float) $order->shipping_total, 2, '.', ''), 1];
        }

        if ((float) $order->discount_total > 0) {
            $items[] = ['İndirim', '-'.number_format((float) $order->discount_total, 2, '.', ''), 1];
        }

        return base64_encode(json_encode($items, JSON_UNESCAPED_UNICODE));
    }

    private function addressLine(Order $order): string
    {
        $a = $order->shipping_address ?? [];

        return trim(implode(' ', array_filter([
            $a['line1'] ?? null,
            $a['line2'] ?? null,
            $a['district'] ?? null,
            $a['city'] ?? null,
        ]))) ?: '-';
    }
}
