@extends('layout.vitrin')

@section('baslik', config('shop.ad') . ' — Edirne')
@section('aciklama', 'Zeys Fashion House, Edirne. Özenle seçilmiş kadın giyim koleksiyonları.')

@push('yapisal_veri')
    @include('vitrin.parca.jsonld', ['tur' => 'magaza'])
@endpush

@php
    $esik = (float) config('shop.kargo.ucretsiz_esigi');
    $sekmeler = array_filter([
        'yeni' => ['Yeni Gelenler', $yeniUrunler],
        'cok-satan' => ['Çok Satanlar', $cokSatanlar],
        'begenilen' => ['En Beğenilenler', $begenilenler],
    ], fn ($s) => $s[1]->isNotEmpty());
    $listeler = array_filter([
        'Öne Çıkanlar' => $oneCikanlar,
        'Yeni Gelenler' => $yeniUrunler->take(3),
        'Çok Satanlar' => $cokSatanlar->take(3),
    ], fn ($l) => $l->isNotEmpty());
@endphp

@section('icerik')

    @if (session('bilgi'))
        <div class="kap" style="margin-top:20px"><p class="uyari">{{ session('bilgi') }}</p></div>
    @endif

    {{-- 1. Slayt — görselli koleksiyonlar; yoksa markanın kendi slaytı --}}
    <section class="slayt" aria-label="Öne çıkanlar" data-slayt>
        <div class="slayt-ray">
            @forelse ($slaytlar as $slayt)
                <div class="slayt-oge" id="slayt-{{ $loop->iteration }}"
                     style="background-image: url('{{ asset('storage/' . $slayt->image) }}')">
                    <div class="kap slayt-ic">
                        <div class="slayt-metin">
                            <p class="slayt-vurgu">Yeni koleksiyon</p>
                            <h2 class="slayt-baslik">{{ $slayt->name }}</h2>
                            @if ($slayt->description)
                                <p class="slayt-alt">{{ $slayt->description }}</p>
                            @endif
                            <div class="slayt-dugmeler">
                                <a class="dugme" href="{{ route('collections.show', $slayt->slug) }}">Alışverişe başla</a>
                                <a class="dugme dugme-cizgi" href="{{ url('/koleksiyonlar') }}">Tüm ürünler</a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="slayt-oge slayt-marka" id="slayt-1">
                    <div class="kap slayt-ic">
                        <img class="slayt-logo" src="{{ asset('img/zeys-logo.png') }}"
                             alt="{{ config('shop.ad') }}" width="340" height="251">
                        <div class="slayt-metin">
                            <p class="slayt-vurgu">Edirne'den kapınıza</p>
                            <h1 class="slayt-baslik">Zarafet, güncel bir dille</h1>
                            <p class="slayt-alt">
                                Butiğimizde özenle seçtiğimiz kadın giyim parçaları artık çevrim içi.
                                @if ($esik > 0)
                                    {{ number_format($esik, 0, ',', '.') }} TL ve üzeri siparişlerde kargo ücretsiz.
                                @endif
                            </p>
                            <div class="slayt-dugmeler">
                                <a class="dugme" href="{{ url('/koleksiyonlar') }}">Alışverişe başla</a>
                                <a class="dugme dugme-cizgi" href="{{ url('/iletisim') }}">Mağazayı ziyaret et</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($slaytlar->count() > 1)
            <div class="slayt-noktalar">
                @foreach ($slaytlar as $slayt)
                    <a href="#slayt-{{ $loop->iteration }}" aria-label="{{ $loop->iteration }}. slayt"
                       @class(['aktif' => $loop->first])></a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- 2. Kategori daireleri --}}
    @if ($kategoriler->isNotEmpty())
        <section class="kap bolum">
            <div class="kategori-daireler">
                @foreach ($kategoriler as $kat)
                    <a class="kategori-daire" href="{{ route('catalog.category', $kat->slug) }}">
                        <span class="kategori-daire-gorsel">
                            @if ($kat->image)
                                <img src="{{ asset('storage/' . $kat->image) }}" alt="" loading="lazy">
                            @else
                                <span class="kategori-daire-harf" aria-hidden="true">{{ mb_substr($kat->name, 0, 1) }}</span>
                            @endif
                        </span>
                        <span class="kategori-daire-ad">{{ $kat->name }}</span>
                        <span class="kategori-daire-sayi">{{ $kat->products_count }} ürün</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 3. İndirimdekiler — referanstaki "Deals of the Day", sayaçsız --}}
    @if ($indirimdekiler->isNotEmpty())
        <section class="kap bolum">
            <div class="bolum-basi">
                <h2>Fırsat Ürünleri</h2>
                <a href="{{ url('/koleksiyonlar?sirala=indirim') }}">Tümünü gör</a>
            </div>
            @include('vitrin.parca.urun-serit', ['urunler' => $indirimdekiler])
        </section>
    @endif

    {{-- 4. Koleksiyon afişleri --}}
    @if ($koleksiyonlar->isNotEmpty())
        <section class="kap bolum">
            <div class="afisler afisler-{{ min(3, $koleksiyonlar->count()) }}">
                @foreach ($koleksiyonlar->take(3) as $koleksiyon)
                    <a class="afis {{ $koleksiyon->image ? 'afis-gorselli' : 'afis-duz afis-ton-' . $loop->iteration }}"
                       href="{{ route('collections.show', $koleksiyon->slug) }}"
                       @if ($koleksiyon->image) style="background-image: url('{{ asset('storage/' . $koleksiyon->image) }}')" @endif>
                        <span class="afis-metin">
                            <span class="afis-ust">{{ $koleksiyon->products_count }} parça</span>
                            <span class="afis-baslik">{{ $koleksiyon->name }}</span>
                            @if ($koleksiyon->description)
                                <span class="afis-alt">{{ \Illuminate\Support\Str::limit($koleksiyon->description, 70) }}</span>
                            @endif
                            <span class="afis-bag">Alışverişe başla @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 5. Sekmeli ürünler --}}
    @if ($sekmeler !== [])
        <section class="kap bolum" data-sekmeler>
            <div class="bolum-baslik">
                <h2>Moda Ürünleri</h2>
            </div>

            <div class="sekmeler" role="tablist" aria-label="Ürün grupları">
                @foreach ($sekmeler as $anahtar => [$ad, $liste])
                    <button type="button" class="sekme" role="tab" id="sekme-{{ $anahtar }}"
                            aria-controls="panel-{{ $anahtar }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $ad }}</button>
                @endforeach
            </div>

            @foreach ($sekmeler as $anahtar => [$ad, $liste])
                <div class="sekme-panel" role="tabpanel" id="panel-{{ $anahtar }}" aria-labelledby="sekme-{{ $anahtar }}">
                    <h3 class="gorunmez">{{ $ad }}</h3>
                    <div class="urun-izgara">
                        @foreach ($liste as $urun)
                            @include('vitrin.parca.urun-kart', ['urun' => $urun])
                        @endforeach
                    </div>
                </div>
            @endforeach

            <p class="bolum-alt-bag">
                <a class="dugme dugme-cizgi" href="{{ url('/koleksiyonlar') }}">Tüm ürünleri gör</a>
            </p>
        </section>
    @endif

    {{-- 6. Mağaza afişleri — gerçek bilgi: adres ve iade hakkı --}}
    <section class="kap bolum">
        <div class="afisler afisler-2">
            <a class="afis afis-duz afis-genis afis-ton-1" href="{{ url('/iletisim') }}">
                <span class="afis-metin">
                    <span class="afis-ust">Edirne mağazamız</span>
                    <span class="afis-baslik">Deneyerek alın</span>
                    <span class="afis-alt">{{ config('shop.satici.adres') }}</span>
                    <span class="afis-bag">Yol tarifi ve iletişim @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])</span>
                </span>
                @include('vitrin.parca.ikon', ['ad' => 'magaza', 'sinif' => 'afis-ikon'])
            </a>
            <a class="afis afis-duz afis-genis afis-ton-2" href="{{ url('/sayfa/iade-degisim') }}">
                <span class="afis-metin">
                    <span class="afis-ust">Kolay iade ve değişim</span>
                    <span class="afis-baslik">{{ config('shop.cayma_hakki_gun') }} gün içinde iade</span>
                    <span class="afis-alt">Bedeni olmadı mı? Sipariş sayfanızdan birkaç tıkla değişim talebi açın.</span>
                    <span class="afis-bag">İade koşulları @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])</span>
                </span>
                @include('vitrin.parca.ikon', ['ad' => 'iade', 'sinif' => 'afis-ikon'])
            </a>
        </div>
    </section>

    {{-- 7. Özellik şeridi --}}
    @include('vitrin.parca.ozellikler')

    {{-- 8. Küçük listeler --}}
    @if (count($listeler) > 1)
        <section class="kap bolum">
            <div class="mini-listeler">
                @foreach ($listeler as $baslik => $liste)
                    <div>
                        <div class="bolum-basi"><h2>{{ $baslik }}</h2></div>
                        <ul class="mini-liste">
                            @foreach ($liste as $urun)
                                <li>@include('vitrin.parca.urun-mini', ['urun' => $urun])</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

@endsection
