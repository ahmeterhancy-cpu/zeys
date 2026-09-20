<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderStock;
use App\Services\Payments\PayTrGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class CheckoutController extends Controller
{
    public function form(Request $request, Cart $cart)
    {
        if ($cart->isEmpty()) {
            return redirect()->route('cart.index');
        }

        /*
         * Giris yapmis musterinin varsayilan adresi forma dolduruluyor.
         * Misafir alisverisi degismiyor; adres yoksa form bos acilir.
         */
        $kayitliAdres = $request->user()
            ? Address::where('user_id', $request->user()->id)
                ->orderByDesc('is_default')
                ->first()
            : null;

        return view('vitrin.odeme', [
            'kayitliAdres' => $kayitliAdres,
            'satirlar' => $cart->lines(),
            'araToplam' => $cart->subtotal(),
            'indirim' => $cart->discount(),
            'kargo' => $cart->shipping(),
            'toplam' => $cart->total(),
            'sozlesme' => LegalDocument::current('mesafeli-satis'),
            'onBilgi' => LegalDocument::current('on-bilgilendirme'),
        ]);
    }

    public function store(Request $request, Cart $cart, Checkout $checkout, PayTrGateway $paytr, OrderStock $stock)
    {
        $veri = $request->validate([
            'ad' => ['required', 'string', 'max:120'],
            'eposta' => ['required', 'email', 'max:190'],
            'telefon' => ['required', 'string', 'max:30'],
            'adres' => ['required', 'string', 'max:255'],
            'adres2' => ['nullable', 'string', 'max:255'],
            'ilce' => ['required', 'string', 'max:80'],
            'il' => ['required', 'string', 'max:80'],
            'posta_kodu' => ['nullable', 'string', 'max:12'],
            'not' => ['nullable', 'string', 'max:500'],
            // 6502 sayılı kanun: onay olmadan sipariş alınamaz
            'sozlesme_onay' => ['accepted'],
        ], [
            'sozlesme_onay.accepted' => 'Siparişi tamamlamak için sözleşmeleri onaylamanız gerekir.',
        ]);

        $adres = [
            'name' => $veri['ad'],
            'phone' => $veri['telefon'],
            'line1' => $veri['adres'],
            'line2' => $veri['adres2'] ?? null,
            'district' => $veri['ilce'],
            'city' => $veri['il'],
            'postal_code' => $veri['posta_kodu'] ?? null,
        ];

        $sozlesme = LegalDocument::current('mesafeli-satis');

        try {
            $order = $checkout->place(
                customer: ['name' => $veri['ad'], 'email' => $veri['eposta'], 'phone' => $veri['telefon']],
                shipping: $adres,
                billing: null,
                userId: $request->user()?->id,
                note: $veri['not'] ?? null,
                // Müşterinin onayladığı metnin SÜRÜMÜ saklanır
                contractVersion: $sozlesme?->version,
                ip: $request->ip(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('hata', $e->getMessage());
        }

        $cart->clear();

        return $this->odemeyeDevret($order, $paytr, $stock);
    }

    /**
     * Siparişi ödeme adımına taşır.
     *
     * PayTR kimlik bilgileri tanımlı değilse yerelde bir benzetim ekranı
     * gösterilir. Benzetim CANLIDA ASLA açılmaz: hem kimlik bilgisi yok
     * hem de ortam kontrolü var — ikisinden biri yeterli olsaydı yanlış
     * yapılandırmada gerçek siteye sahte ödeme düğmesi düşebilirdi.
     */
    private function odemeyeDevret(Order $order, PayTrGateway $paytr, OrderStock $stock)
    {
        $okUrl = URL::signedRoute('payment.return', ['order' => $order->number]);
        $failUrl = URL::signedRoute('payment.failed', ['order' => $order->number]);

        if (! $paytr->isConfigured()) {
            if (app()->environment(['local', 'testing'])) {
                return view('vitrin.odeme-benzetim', compact('order'));
            }

            // Canlıda yapılandırma eksikse sipariş askıda kalmasın
            $stock->release($order->fresh('items'));

            return redirect()->route('cart.index')
                ->with('hata', 'Ödeme altyapısı şu anda kullanılamıyor. Lütfen bizimle iletişime geçin.');
        }

        $sonuc = $paytr->createToken($order, $okUrl, $failUrl);

        if (! $sonuc['ok']) {
            $stock->release($order->fresh('items'));

            return redirect()->route('cart.index')
                ->with('hata', 'Ödeme başlatılamadı: '.$sonuc['error']);
        }

        return view('vitrin.odeme-iframe', [
            'order' => $order,
            'iframeUrl' => $paytr->iframeUrl($sonuc['token']),
        ]);
    }
}
