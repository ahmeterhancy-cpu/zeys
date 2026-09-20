@extends('layout.vitrin')

@section('baslik', 'İletişim — ' . config('shop.ad'))
@section('aciklama', 'Zeys Fashion House — Edirne mağaza adresi ve iletişim bilgileri.')

@section('icerik')
<div class="kap kap-dar yasal-sayfa">
    <h1>İletişim</h1>

    <div class="iletisim-blok">
        <h2>Mağaza</h2>
        <address>{{ config('shop.satici.adres') }}</address>
    </div>

    @if (config('shop.satici.telefon'))
        <div class="iletisim-blok">
            <h2>Telefon</h2>
            <p><a href="tel:{{ preg_replace('/\D/', '', config('shop.satici.telefon')) }}">
                {{ config('shop.satici.telefon') }}
            </a></p>
        </div>
    @endif

    @if (config('shop.satici.eposta'))
        <div class="iletisim-blok">
            <h2>E-posta</h2>
            <p><a href="mailto:{{ config('shop.satici.eposta') }}">{{ config('shop.satici.eposta') }}</a></p>
        </div>
    @endif

    <div class="iletisim-blok">
        <h2>Instagram</h2>
        <p>
            <a href="https://instagram.com/{{ config('shop.sosyal.instagram') }}"
               rel="noopener noreferrer" target="_blank">
                &#64;{{ config('shop.sosyal.instagram') }}
            </a>
        </p>
    </div>

    @if (config('shop.satici.unvan'))
        <div class="iletisim-blok">
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
