{{--
    Yatay kayan ürün şeridi (referanstaki karusel). Kaydırma yerel
    scroll-snap; oklar JS ile çalışır, JS yoksa parmakla/tekerle kaydırılır.
--}}
<div class="urun-serit" data-serit>
    <button type="button" class="serit-ok serit-ok-sol" data-serit-yon="-1" aria-label="Önceki ürünler">
        @include('vitrin.parca.ikon', ['ad' => 'ok-sol'])
    </button>
    <div class="urun-serit-ray" tabindex="0" aria-label="Ürünler">
        @foreach ($urunler as $urun)
            <div class="urun-serit-oge">
                @include('vitrin.parca.urun-kart', ['urun' => $urun])
            </div>
        @endforeach
    </div>
    <button type="button" class="serit-ok serit-ok-sag" data-serit-yon="1" aria-label="Sonraki ürünler">
        @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])
    </button>
</div>
