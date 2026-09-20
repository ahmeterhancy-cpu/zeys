@extends('layout.vitrin')

@section('baslik', 'Ödeme — ' . config('shop.ad'))

@section('icerik')
<div class="kap kap-dar odeme-cerceve-sayfa">
    <h1>Ödeme</h1>

    <p class="odeme-cerceve-not">
        Sipariş numaranız <strong>{{ $order->number }}</strong>.
        Kart bilgilerinizi aşağıdaki güvenli alanda giriyorsunuz;
        bu bilgiler bizim sunucumuzdan geçmez.
    </p>

    {{--
        PayTR iFrame. Kart verisi yalnizca PayTR'nin sayfasina girilir.
        Yukseklik otomatik ayarlanamiyor (capraz kaynak), o yuzden bol
        tutuldu; icerik kisaysa bos alan kalir ama form kesilmez.
    --}}
    <iframe src="{{ $iframeUrl }}"
            id="paytrIframe"
            frameborder="0"
            scrolling="no"
            title="Güvenli ödeme"
            class="odeme-cerceve"></iframe>

    <p class="odeme-cerceve-not odeme-cerceve-kucuk">
        Ödeme tamamlandığında bu sayfadan otomatik olarak yönlendirileceksiniz.
        Sayfayı kapatmayın.
    </p>
</div>
@endsection
