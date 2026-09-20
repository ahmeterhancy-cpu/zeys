<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Yerel geliştirme için sahte ödeme sonucu.
 *
 * ⚠️ CANLIDA YOKTUR. İki kilit var ve ikisi de tek başına yeterli:
 *   1) ortam local/testing değilse 404
 *   2) PayTR kimlik bilgileri tanımlıysa 404
 *
 * Tek kilit bırakmak istemedim: yanlış yapılandırılmış bir canlı
 * kurulumda "ödemeyi başarılı say" düğmesi gerçek siteye düşerse
 * bedava sipariş verilebilir.
 */
class PaymentSimulationController extends Controller
{
    public function __invoke(Request $request, OrderPayments $payments)
    {
        $this->kilitKontrol();

        $veri = $request->validate([
            'order' => ['required', 'string'],
            'sonuc' => ['required', 'in:basarili,basarisiz'],
        ]);

        $order = Order::where('number', $veri['order'])->firstOrFail();

        if ($veri['sonuc'] === 'basarili') {
            $payments->markPaid($order, ['benzetim' => true, 'payment_type' => 'simulasyon']);

            return redirect(URL::signedRoute('payment.return', ['order' => $order->number]));
        }

        $payments->markFailed($order, 'Benzetim: ödeme başarısız seçildi');

        return redirect(URL::signedRoute('payment.failed', ['order' => $order->number]));
    }

    private function kilitKontrol(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new NotFoundHttpException;
        }

        if (config('paytr.merchant_id') !== '' && config('paytr.merchant_key') !== '') {
            throw new NotFoundHttpException;
        }
    }
}
