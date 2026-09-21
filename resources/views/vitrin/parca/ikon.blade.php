{{--
    Çizgi ikonlar — kütüphane yok, satır içi SVG.
    Kullanım: @include('vitrin.parca.ikon', ['ad' => 'sepet'])
    Dekoratiftir (aria-hidden); anlamı daima yanındaki metin ya da
    düğmenin aria-label'ı taşır.
--}}
@php
    $yollar = [
        'arama' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'kullanici' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        'sepet' => '<path d="M5 7h14l-1.2 12.1a2 2 0 0 1-2 1.9H8.2a2 2 0 0 1-2-1.9Z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'kapat' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'ev' => '<path d="M4 11 12 4l8 7"/><path d="M6 10v10h12V10"/>',
        'magaza' => '<path d="M4 9 5.5 4h13L20 9"/><path d="M4 9h16v2a3 3 0 0 1-5.3 1.9A3 3 0 0 1 12 14a3 3 0 0 1-2.7-1.1A3 3 0 0 1 4 11Z"/><path d="M5 13v7h14v-7"/>',
        'telefon' => '<path d="M6.6 3h2.8l1.4 4.2-2 1.4a11 11 0 0 0 6.6 6.6l1.4-2 4.2 1.4v2.8A2 2 0 0 1 19 20 16 16 0 0 1 4 5a2 2 0 0 1 2.6-2Z"/>',
        'eposta' => '<rect x="3" y="5" width="18" height="14"/><path d="m3 6 9 7 9-7"/>',
        'konum' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'kamyon' => '<path d="M2 6h11v10H2z"/><path d="M13 9h4l4 4v3h-8"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'kalkan' => '<path d="M12 3 5 6v5c0 4.4 3 8.3 7 10 4-1.7 7-5.6 7-10V6Z"/><path d="m9 12 2 2 4-4"/>',
        'iade' => '<path d="M4 9h11a5 5 0 0 1 0 10H9"/><path d="M8 5 4 9l4 4"/>',
        'destek' => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6"/><rect x="17" y="13" width="4" height="6"/><path d="M19 19a3 3 0 0 1-3 2h-3"/>',
        'instagram' => '<rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r=".6" fill="currentColor"/>',
        'ok-sag' => '<path d="m9 6 6 6-6 6"/>',
        'ok-sol' => '<path d="m15 6-6 6 6 6"/>',
        'ok-asagi' => '<path d="m6 9 6 6 6-6"/>',
        'ok-yukari' => '<path d="m6 15 6-6 6 6"/>',
        'saat' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'filtre' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'kategori' => '<rect x="4" y="4" width="6.5" height="6.5"/><rect x="13.5" y="4" width="6.5" height="6.5"/><rect x="4" y="13.5" width="6.5" height="6.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5"/>',
        'goz' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
    ];
@endphp
<svg class="ikon {{ $sinif ?? '' }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">{!! $yollar[$ad] ?? '' !!}</svg>
