<?php

namespace App\Http\Controllers;

use App\Services\OrderPayments;
use App\Services\Payments\PayTrGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PayTR sunucu-sunucu bildirimi.
 *
 * Siparişi "ödendi" yapan TEK yer burasıdır; tarayıcının dönüş adresine
 * düşmesi bir şey kanıtlamaz.
 *
 * PayTR, gövdesi tam olarak "OK" olmayan her yanıtı başarısız sayar ve
 * bildirimi tekrar gönderir. Bu yüzden imza doğrulandıktan sonra, iş
 * mantığı ne olursa olsun "OK" döneriz — aksi hâlde sonsuz tekrar.
 */
class PayTrCallbackController extends Controller
{
    public function __invoke(Request $request, PayTrGateway $paytr, OrderPayments $payments)
    {
        $payload = $request->all();

        if (! $paytr->verifyCallback($payload)) {
            Log::warning('PayTR bildirimi doğrulanamadı', ['oid' => $payload['merchant_oid'] ?? null]);

            // İmza tutmuyorsa bu bildirim PayTR'den gelmiş sayılmaz.
            return response('PAYTR notification failed: bad hash', 400);
        }

        $order = $paytr->resolveOrder((string) ($payload['merchant_oid'] ?? ''));

        if (! $order) {
            Log::warning('PayTR bildirimi: sipariş bulunamadı', ['oid' => $payload['merchant_oid'] ?? null]);

            return response('OK');
        }

        if ($order->payment_status === 'paid') {
            return response('OK');
        }

        if (($payload['status'] ?? '') !== 'success') {
            $payments->markFailed($order, (string) ($payload['failed_reason_msg'] ?? ''));

            return response('OK');
        }

        $expected = $paytr->kurus((float) $order->grand_total);
        $received = (int) ($payload['total_amount'] ?? 0);

        /*
         * Beklenenden AZ para geldiyse sipariş ödendi sayılmaz — eksik
         * tahsilatla kargo çıkmasın. Fazla gelmesi normaldir: taksit
         * komisyonu müşteriye yansıtıldığında total_amount büyür.
         */
        if ($received < $expected) {
            $payments->flagAmountMismatch($order, $expected, $received);

            return response('OK');
        }

        $payments->markPaid($order, [
            'payment_type' => $payload['payment_type'] ?? null,
            'total_amount' => $received,
            'installment_count' => $payload['installment_count'] ?? null,
            'callback_at' => now()->toIso8601String(),
        ]);

        return response('OK');
    }
}
