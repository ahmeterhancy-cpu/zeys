<?php

namespace App\Http\Controllers;

use App\Models\Order;

/**
 * Müşterinin ödeme sonrası düştüğü sayfa.
 *
 * Bu sayfaya düşmek ödemenin başarılı olduğu anlamına GELMEZ ve burada
 * sipariş durumu DEĞİŞTİRİLMEZ. Siparişi ödendi yapan tek yer imzası
 * doğrulanmış callback'tir.
 *
 * Callback bazen tarayıcı dönüşünden birkaç saniye sonra ulaşır; o aralıkta
 * müşteriye "ödemeniz işleniyor" gösterilir, "başarılı" değil.
 */
class PaymentReturnController extends Controller
{
    public function ok(Order $order)
    {
        return view('odeme.donus', [
            'order' => $order,
            'durum' => $order->payment_status === 'paid' ? 'basarili' : 'isleniyor',
        ]);
    }

    public function fail(Order $order)
    {
        return view('odeme.donus', [
            'order' => $order,
            // Başarısızlığı da callback kesinleştirir; burada yalnızca
            // müşteriye geri bildirim veriyoruz.
            'durum' => $order->payment_status === 'paid' ? 'basarili' : 'basarisiz',
        ]);
    }
}
