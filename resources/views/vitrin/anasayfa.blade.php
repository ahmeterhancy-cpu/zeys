@extends('layout.vitrin')

@section('baslik', config('shop.ad') . ' — Edirne')
@section('aciklama', 'Zeys Fashion House, Edirne. Özenle seçilmiş kadın giyim koleksiyonları.')

@section('icerik')

    {{-- Açılış: dev bir stok fotoğrafı yerine markanın kendi işareti --}}
    <section class="acilis bolum-ilk">
        <div class="kap">
            <img class="acilis-logo" src="{{ asset('img/zeys-logo.png') }}"
                 alt="{{ config('shop.ad') }}" width="340" height="251">

            <h1>Zarafet, güncel bir dille</h1>

            <p>
                Edirne'deki butiğimizde özenle seçtiğimiz parçaları
                artık çevrim içi de bulabilirsiniz.
            </p>

            <div class="acilis-dugmeler">
                <a class="dugme" href="{{ url('/koleksiyonlar') }}">Koleksiyonları gör</a>
                <a class="dugme dugme-cizgi" href="{{ url('/iletisim') }}">Mağazayı ziyaret et</a>
            </div>
        </div>
    </section>

    @if ($yeniUrunler->isNotEmpty())
        <section class="bolum belir">
            <div class="kap">
                <div class="bolum-basi">
                    <h2>Yeni Gelenler</h2>
                    <a href="{{ url('/koleksiyonlar') }}">Tümünü gör</a>
                </div>

                <div class="urun-izgara">
                    @foreach ($yeniUrunler as $urun)
                        @include('vitrin.parca.urun-kart', ['urun' => $urun])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($koleksiyonlar->isNotEmpty())
        <section class="bolum belir">
            <div class="kap">
                <div class="bolum-basi">
                    <h2>Koleksiyonlar</h2>
                </div>

                <div class="koleksiyon-izgara">
                    @foreach ($koleksiyonlar as $koleksiyon)
                        <a class="koleksiyon-kart" href="{{ url('/koleksiyon/' . $koleksiyon->slug) }}">
                            <div>
                                <h3>{{ $koleksiyon->name }}</h3>
                                @if ($koleksiyon->description)
                                    <p>{{ $koleksiyon->description }}</p>
                                @endif
                                <span class="etiket">{{ $koleksiyon->products_count }} parça</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="bolum belir">
        <div class="kap">
            <div class="magaza">
                <div>
                    <span class="etiket">Mağaza</span>
                    <h2 style="margin-top:10px">Edirne'de bizi bekliyoruz</h2>
                    <address>{{ config('shop.satici.adres') }}</address>

                    <div class="magaza-baglar">
                        <a class="dugme dugme-cizgi"
                           href="https://instagram.com/{{ config('shop.sosyal.instagram') }}"
                           rel="noopener noreferrer" target="_blank">
                            Instagram
                        </a>
                        <a class="dugme dugme-cizgi" href="{{ url('/iletisim') }}">İletişim</a>
                    </div>
                </div>

                <div>
                    <p class="etiket">Kargo ve İade</p>
                    <p style="margin-top:10px; color: var(--ink-soft)">
                        {{ number_format((float) config('shop.kargo.ucretsiz_esigi'), 0, ',', '.') }} TL
                        ve üzeri siparişlerde kargo ücretsiz. Altındaki siparişlerde
                        {{ number_format((float) config('shop.kargo.ucret'), 0, ',', '.') }} TL kargo bedeli alınır.
                    </p>
                    <p class="magaza-not">
                        Teslim tarihinden itibaren {{ config('shop.cayma_hakki_gun') }} gün içinde
                        iade ve değişim hakkınız vardır.
                    </p>
                </div>
            </div>
        </div>
    </section>

@endsection
