@extends('layout.vitrin')

@section('baslik', 'İletişim — ' . config('shop.ad'))
@section('aciklama', 'Zeys Fashion House — Edirne mağaza adresi, telefon, WhatsApp ve yol tarifi.')

@section('icerik')
@include('vitrin.parca.sayfa-basi', ['baslik' => 'İletişim'])

@php
    $adres = config('shop.satici.adres');
    $telefon = config('shop.satici.telefon');
    $eposta = config('shop.satici.eposta');
    $saatler = config('shop.iletisim.calisma_saatleri');
    $wa = \App\Support\Iletisim::whatsappBaglanti();
    $waGosterim = \App\Support\Iletisim::whatsappGosterim();
    $harita = \App\Support\Iletisim::haritaGomme();
    $yolTarifi = \App\Support\Iletisim::yolTarifi();
@endphp

<div class="kap iletisim-sayfa">

    <div class="iletisim-duzen">

        {{-- Sol: ulaşma yolları --}}
        <div class="iletisim-kanallar">
            <p class="iletisim-giris">
                Edirne’deki mağazamıza bekleriz. Beden, stok ve kargo sorularınız için
                en hızlı yanıtı WhatsApp’tan alırsınız.
            </p>

            <div class="iletisim-kart">
                <span class="iletisim-ikon">@include('vitrin.parca.ikon', ['ad' => 'konum'])</span>
                <div>
                    <h2>Mağaza</h2>
                    <address>{{ $adres }}</address>
                    @if ($yolTarifi)
                        <a class="iletisim-bag" href="{{ $yolTarifi }}" target="_blank" rel="noopener noreferrer">
                            Yol tarifi al
                            @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])
                        </a>
                    @endif
                </div>
            </div>

            @if ($wa)
                <div class="iletisim-kart">
                    <span class="iletisim-ikon">@include('vitrin.parca.ikon', ['ad' => 'destek'])</span>
                    <div>
                        <h2>WhatsApp</h2>
                        <p><a href="{{ $wa }}" target="_blank" rel="noopener noreferrer">{{ $waGosterim }}</a></p>
                        <p class="iletisim-not">Mesajınıza mağaza saatleri içinde dönüş yapılır.</p>
                    </div>
                </div>
            @endif

            @if ($telefon)
                <div class="iletisim-kart">
                    <span class="iletisim-ikon">@include('vitrin.parca.ikon', ['ad' => 'telefon'])</span>
                    <div>
                        <h2>Telefon</h2>
                        <p><a href="tel:{{ preg_replace('/\D/', '', $telefon) }}">{{ $telefon }}</a></p>
                    </div>
                </div>
            @endif

            @if ($eposta)
                <div class="iletisim-kart">
                    <span class="iletisim-ikon">@include('vitrin.parca.ikon', ['ad' => 'eposta'])</span>
                    <div>
                        <h2>E-posta</h2>
                        <p><a href="mailto:{{ $eposta }}">{{ $eposta }}</a></p>
                    </div>
                </div>
            @endif

            @if ($saatler)
                <div class="iletisim-kart">
                    <span class="iletisim-ikon">@include('vitrin.parca.ikon', ['ad' => 'saat'])</span>
                    <div>
                        <h2>Çalışma saatleri</h2>
                        <p>{{ $saatler }}</p>
                    </div>
                </div>
            @endif

            <div class="iletisim-kart">
                <span class="iletisim-ikon">@include('vitrin.parca.ikon', ['ad' => 'instagram'])</span>
                <div>
                    <h2>Instagram</h2>
                    <p>
                        <a href="https://instagram.com/{{ config('shop.sosyal.instagram') }}"
                           rel="noopener noreferrer" target="_blank">
                            &#64;{{ config('shop.sosyal.instagram') }}
                        </a>
                    </p>
                </div>
            </div>
        </div>

        {{-- Sağ: harita --}}
        <div class="iletisim-harita-kutu">
            @if ($harita)
                {{--
                    Harita gömülü iframe; tembel yüklenir ki sayfa açılışını
                    yavaşlatmasın. Adresten üretilir, API anahtarı gerekmez.
                --}}
                <div class="harita-cerceve">
                    <iframe src="{{ $harita }}"
                            title="Zeys Fashion House — mağaza konumu"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen></iframe>
                </div>
            @else
                <div class="harita-cerceve harita-yok">
                    <p>Mağaza adresi girildiğinde harita burada görünür.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Sipariş sorularını doğru yere yönlendir --}}
    <div class="iletisim-yonlendirme">
        <a class="yonlendirme-kart" href="{{ route('order.lookup.form') }}">
            <span>Siparişimi sorgula</span>
            <small>Sipariş numarası ve e-posta ile durumu görün.</small>
        </a>
        <a class="yonlendirme-kart" href="{{ url('/sayfa/iade-degisim') }}">
            <span>İade ve değişim</span>
            <small>Beden tutmadıysa nasıl ilerleyeceğinizi anlatır.</small>
        </a>
        <a class="yonlendirme-kart" href="{{ url('/sayfa/mesafeli-satis') }}">
            <span>Mesafeli satış sözleşmesi</span>
            <small>Satış koşulları ve cayma hakkı.</small>
        </a>
    </div>

    @if (config('shop.satici.unvan'))
        <div class="iletisim-satici">
            <h2>Satıcı bilgileri</h2>
            <p>
                {{ config('shop.satici.unvan') }}<br>
                @if (config('shop.satici.mersis'))
                    MERSİS: {{ config('shop.satici.mersis') }}<br>
                @endif
                @if (config('shop.satici.vergi_no'))
                    Vergi No: {{ config('shop.satici.vergi_no') }}
                    ({{ config('shop.satici.vergi_dairesi') }})
                @endif
            </p>
        </div>
    @endif
</div>
@endsection
