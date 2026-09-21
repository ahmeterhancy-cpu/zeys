@extends('layout.vitrin')

@section('baslik', 'Ödeme (benzetim) — ' . config('shop.ad'))

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'Ödeme benzetimi'])

<div class="kap kap-dar odeme-cerceve-sayfa">
    <p class="uyari uyari-hata">
        <strong>Bu ekran yalnızca yerel geliştirme içindir.</strong>
        PayTR kimlik bilgileri tanımlı olmadığı için gerçek ödeme sayfası
        açılamadı. Canlı ortamda bu ekran görünmez.
    </p>


    <p class="odeme-cerceve-not">
        Sipariş <strong>{{ $order->number }}</strong> ·
        Tutar <strong>{{ number_format((float) $order->grand_total, 2, ',', '.') }} TL</strong>
    </p>

    <div class="benzetim-dugmeler">
        <form method="POST" action="{{ route('payment.simulate') }}">
            @csrf
            <input type="hidden" name="order" value="{{ $order->number }}">
            <input type="hidden" name="sonuc" value="basarili">
            <button type="submit" class="dugme">Ödemeyi başarılı say</button>
        </form>

        <form method="POST" action="{{ route('payment.simulate') }}">
            @csrf
            <input type="hidden" name="order" value="{{ $order->number }}">
            <input type="hidden" name="sonuc" value="basarisiz">
            <button type="submit" class="dugme dugme-cizgi">Ödemeyi başarısız say</button>
        </form>
    </div>
</div>
@endsection
