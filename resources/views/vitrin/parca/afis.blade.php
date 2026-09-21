{{--
    Tek afiş. $afis: ['ust','baslik','alt','dugme','adres','dis','gorsel','ikon'?]
    (anasayfa.blade.php'deki tek biçim). $sira: görselsiz afişin zemin tonu.
    Bağlantısız afiş <div> olarak çizilir — tıklanmayan <a> olmasın.
--}}
@php
    $etiket = $afis['adres'] ? 'a' : 'div';
    $siniflar = 'afis ' . ($genis ?? false ? 'afis-genis ' : '') . ($afis['gorsel'] ? 'afis-gorselli' : 'afis-duz afis-ton-' . (($sira - 1) % 3 + 1));
@endphp
<{{ $etiket }} class="{{ $siniflar }}"
    @if ($afis['adres']) href="{{ $afis['adres'] }}" @if ($afis['dis']) rel="noopener" target="_blank" @endif @endif
    @if ($afis['gorsel']) style="background-image: url('{{ $afis['gorsel'] }}')" @endif>
    <span class="afis-metin">
        @if ($afis['ust'])
            <span class="afis-ust">{{ $afis['ust'] }}</span>
        @endif
        <span class="afis-baslik">{{ $afis['baslik'] }}</span>
        @if ($afis['alt'])
            <span class="afis-alt">{{ $afis['alt'] }}</span>
        @endif
        @if ($afis['adres'] && $afis['dugme'])
            <span class="afis-bag">{{ $afis['dugme'] }} @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])</span>
        @endif
    </span>
    @if (! $afis['gorsel'] && ! empty($afis['ikon']))
        @include('vitrin.parca.ikon', ['ad' => $afis['ikon'], 'sinif' => 'afis-ikon'])
    @endif
</{{ $etiket }}>
