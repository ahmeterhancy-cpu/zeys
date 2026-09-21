<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\LegalDocument;
use App\Models\Order;
use App\Rules\TcKimlikNo;
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

            /*
             * Fatura (e-Arşiv). Bireyselde TCKN İSTEĞE BAĞLI — vermeyen
             * müşteri için muhasebe tarafı GİB'in genel numarasını kullanır;
             * zorunlu tutmak satışı kaçırır. Verilirse sağlaması doğrulanır.
             * Kurumsalda unvan, vergi dairesi ve VKN zorunlu.
             */
            'fatura_tipi' => ['nullable', 'in:bireysel,kurumsal'],
            'tckn' => ['nullable', new TcKimlikNo],
            'firma_unvani' => ['required_if:fatura_tipi,kurumsal', 'nullable', 'string', 'max:190'],
            'vergi_dairesi' => ['required_if:fatura_tipi,kurumsal', 'nullable', 'string', 'max:120'],
            // Yalnız biçim: VKN sağlama algoritmasından emin değiliz; yanlış
            // bir kontrol geçerli bir kurumsal müşteriyi kasada kilitlerdi.
            'vkn' => ['required_if:fatura_tipi,kurumsal', 'nullable', 'regex:/^[0-9]{10}$/'],

            'farkli_fatura_adresi' => ['nullable', 'boolean'],
            'fatura_adres' => ['required_if:farkli_fatura_adresi,1', 'nullable', 'string', 'max:255'],
            'fatura_ilce' => ['required_if:farkli_fatura_adresi,1', 'nullable', 'string', 'max:80'],
            'fatura_il' => ['required_if:farkli_fatura_adresi,1', 'nullable', 'string', 'max:80'],

            // 6502 sayılı kanun: onay olmadan sipariş alınamaz
            'sozlesme_onay' => ['accepted'],
        ], [
            'sozlesme_onay.accepted' => 'Siparişi tamamlamak için sözleşmeleri onaylamanız gerekir.',
            'firma_unvani.required_if' => 'Kurumsal faturada firma unvanı zorunlu.',
            'vergi_dairesi.required_if' => 'Kurumsal faturada vergi dairesi zorunlu.',
            'vkn.required_if' => 'Kurumsal faturada vergi numarası zorunlu.',
            'vkn.regex' => 'Vergi numarası 10 haneli olmalı.',
            'fatura_adres.required_if' => 'Fatura adresini girin.',
            'fatura_ilce.required_if' => 'Fatura ilçesini girin.',
            'fatura_il.required_if' => 'Fatura ilini girin.',
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

        $kurumsal = ($veri['fatura_tipi'] ?? 'bireysel') === 'kurumsal';
        $farkliAdres = (bool) ($veri['farkli_fatura_adresi'] ?? false);

        // Fatura anlık görüntüsü: adres + vergi kimliği birlikte saklanır
        $fatura = array_merge(
            $farkliAdres
                ? [
                    'name' => $kurumsal ? $veri['firma_unvani'] : $veri['ad'],
                    'phone' => $veri['telefon'],
                    'line1' => $veri['fatura_adres'],
                    'line2' => null,
                    'district' => $veri['fatura_ilce'],
                    'city' => $veri['fatura_il'],
                    'postal_code' => null,
                ]
                : array_merge($adres, ['name' => $kurumsal ? $veri['firma_unvani'] : $veri['ad']]),
            [
                'invoice_type' => $kurumsal ? 'corporate' : 'individual',
                'company_name' => $kurumsal ? $veri['firma_unvani'] : null,
                'tax_office' => $kurumsal ? $veri['vergi_dairesi'] : null,
                'tax_number' => $kurumsal
                    ? $veri['vkn']
                    : (filled($veri['tckn'] ?? null) ? preg_replace('/\s+/', '', $veri['tckn']) : null),
            ]
        );

        $sozlesme = LegalDocument::current('mesafeli-satis');

        try {
            $order = $checkout->place(
                customer: ['name' => $veri['ad'], 'email' => $veri['eposta'], 'phone' => $veri['telefon']],
                shipping: $adres,
                billing: $fatura,
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
