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

    /*
     * Slayt ve afişler tek biçime getirilir. Kaynak sırası:
     * panelden girilenler (Vitrin → Slayt ve Afişler) → görselli koleksiyonlar
     * → sabit içerik. Görünüm yalnız bu listeleri çizer.
     */
    $bannerBicim = fn ($b) => [
        'ust' => $b->ust_metin,
        'baslik' => $b->baslik,
        'alt' => $b->alt_metin,
        'dugme' => $b->dugme_metni ?: ($b->adres ? 'Alışverişe başla' : null),
        'adres' => $b->adres,
        'dis' => $b->dis_baglanti,
        'gorsel' => $b->gorsel ? asset('storage/' . $b->gorsel) : null,
        'konum' => $b->metin_konumu,
    ];

    $slaytListesi = $bannerSlayt->isNotEmpty()
        ? $bannerSlayt->map($bannerBicim)->all()
        : $slaytlar->map(fn ($k) => [
            'ust' => 'Yeni koleksiyon', 'baslik' => $k->name, 'alt' => $k->description,
            'dugme' => 'Alışverişe başla', 'adres' => route('collections.show', $k->slug), 'dis' => false,
            'gorsel' => asset('storage/' . $k->image), 'konum' => 'sag',
        ])->all();

    $afisListesi = $bannerAfis->isNotEmpty()
        ? $bannerAfis->map($bannerBicim)->all()
        : $koleksiyonlar->take(3)->map(fn ($k) => [
            'ust' => $k->products_count . ' parça', 'baslik' => $k->name,
            'alt' => $k->description ? \Illuminate\Support\Str::limit($k->description, 70) : null,
            'dugme' => 'Alışverişe başla', 'adres' => route('collections.show', $k->slug), 'dis' => false,
            'gorsel' => $k->image ? asset('storage/' . $k->image) : null, 'konum' => 'sol',
        ])->all();

    $genisListesi = $bannerGenis->isNotEmpty()
        ? $bannerGenis->map($bannerBicim)->all()
        : [
            ['ust' => 'Edirne mağazamız', 'baslik' => 'Deneyerek alın', 'alt' => config('shop.satici.adres'),
             'dugme' => 'Yol tarifi ve iletişim', 'adres' => url('/iletisim'), 'dis' => false, 'gorsel' => null, 'konum' => 'sol', 'ikon' => 'magaza'],
            ['ust' => 'Kolay iade ve değişim', 'baslik' => config('shop.cayma_hakki_gun') . ' gün içinde iade',
             'alt' => 'Bedeni olmadı mı? Sipariş sayfanızdan birkaç tıkla değişim talebi açın.',
             'dugme' => 'İade koşulları', 'adres' => url('/sayfa/iade-degisim'), 'dis' => false, 'gorsel' => null, 'konum' => 'sol', 'ikon' => 'iade'],
        ];
@endphp

@section('icerik')

    @if (session('bilgi'))
        <div class="kap" style="margin-top:20px"><p class="uyari">{{ session('bilgi') }}</p></div>
    @endif

    {{-- 1. Slayt — panel slaytları; yoksa görselli koleksiyonlar; yoksa markanın kendi slaytı --}}
    <section class="slayt" aria-label="Öne çıkanlar" data-slayt>
        <div class="slayt-ray">
            @forelse ($slaytListesi as $slayt)
                <div @class(['slayt-oge', 'slayt-marka' => ! $slayt['gorsel']]) id="slayt-{{ $loop->iteration }}"
                     @if ($slayt['gorsel']) style="background-image: url('{{ $slayt['gorsel'] }}')" @endif>
                    <div @class(['kap', 'slayt-ic', 'slayt-ic-sol' => $slayt['konum'] === 'sol'])>
                        <div class="slayt-metin">
                            @if ($slayt['ust'])
                                <p class="slayt-vurgu">{{ $slayt['ust'] }}</p>
                            @endif
                            @if ($loop->first)
                                <h1 class="slayt-baslik">{{ $slayt['baslik'] }}</h1>
                            @else
                                <h2 class="slayt-baslik">{{ $slayt['baslik'] }}</h2>
                            @endif
                            @if ($slayt['alt'])
                                <p class="slayt-alt">{{ $slayt['alt'] }}</p>
                            @endif
                            <div class="slayt-dugmeler">
                                @if ($slayt['adres'])
                                    <a class="dugme" href="{{ $slayt['adres'] }}"
                                       @if ($slayt['dis']) rel="noopener" target="_blank" @endif>{{ $slayt['dugme'] }}</a>
                                @endif
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

        @if (count($slaytListesi) > 1)
            <div class="slayt-noktalar">
                @foreach ($slaytListesi as $slayt)
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

    {{-- 4. Üçlü afiş — panel afişleri; yoksa koleksiyonlar --}}
    @if ($afisListesi !== [])
        <section class="kap bolum">
            <div class="afisler afisler-{{ min(3, count($afisListesi)) }}">
                @foreach ($afisListesi as $afis)
                    @include('vitrin.parca.afis', ['afis' => $afis, 'sira' => $loop->iteration])
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

    {{-- 6. İkili geniş afiş — panel afişleri; yoksa mağaza ve iade bilgisi --}}
    <section class="kap bolum">
        <div class="afisler afisler-{{ min(2, count($genisListesi)) }}">
            @foreach ($genisListesi as $afis)
                @include('vitrin.parca.afis', ['afis' => $afis, 'sira' => $loop->iteration, 'genis' => true])
            @endforeach
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
