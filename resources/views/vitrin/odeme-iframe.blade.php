@extends('layout.vitrin')

@section('baslik', 'Ödeme — ' . config('shop.ad'))

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Ödeme'])

<div class="kap kap-dar odeme-cerceve-sayfa">

    <p class="odeme-cerceve-not">
        Sipariş numaranız <strong>{{ $order->number }}</strong>.
        Kart bilgilerinizi aşağıdaki güvenli alanda giriyorsunuz;
        bu bilgiler bizim sunucumuzdan geçmez.
    </p>

    {{--
        PayTR iFrame. Kart verisi yalnizca PayTR'nin sayfasina girilir.

        TUZAK (canlida gorundu): sabit yukseklik + scrolling="no" ile
        PayTR'nin icerigi uzadiginda (test modu uyarisi, 3D adimi, taksit
        tablosu) "Ode" dugmesi KESILIYOR ve kaydirilamiyor.

        Cozum PayTR'nin kendi boy ayarlayicisi — belgelerinde onerilen
        yontem bu. Betik yuklenemezse scrolling varsayilanda kalir ve
        cerceve kendi icinde kaydirilabilir; dugme yine erisilebilir.
    --}}
    <iframe src="{{ $iframeUrl }}"
            id="paytrIframe"
            frameborder="0"
            title="Güvenli ödeme"
            class="odeme-cerceve"></iframe>

    <p class="odeme-cerceve-not odeme-cerceve-kucuk">
        Ödeme tamamlandığında bu sayfadan otomatik olarak yönlendirileceksiniz.
        Sayfayı kapatmayın.
    </p>
</div>
@endsection

@push('betik')
    <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
    <script>
        // PayTR'nin kendi betigi; cerceveyi icerik boyuna gore buyutur.
        if (window.iFrameResize) {
            iFrameResize({ checkOrigin: false }, '#paytrIframe');
        }
    </script>
@endpush
