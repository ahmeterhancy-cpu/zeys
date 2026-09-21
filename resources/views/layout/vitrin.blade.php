<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('baslik', config('shop.ad'))</title>
    <meta name="description" content="@yield('aciklama', 'Zeys Fashion House — Edirne. Kadın giyim koleksiyonları.')">
    <link rel="icon" href="{{ asset('img/zeys-logo-sm.png') }}" type="image/png">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Acik Grafik: baglanti paylasildiginda kart gorunumu --}}
    <meta property="og:site_name" content="{{ config('shop.ad') }}">
    <meta property="og:type" content="@yield('og_tur', 'website')">
    <meta property="og:title" content="@yield('baslik', config('shop.ad'))">
    <meta property="og:description" content="@yield('aciklama', 'Zeys Fashion House — Edirne. Kadın giyim koleksiyonları.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_gorsel', asset('img/zeys-logo.png'))">
    <meta property="og:locale" content="tr_TR">
    <meta name="twitter:card" content="summary_large_image">

    @stack('yapisal_veri')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body id="ust">
    <a href="#icerik" class="gorunmez">İçeriğe atla</a>

    {{-- Perde açıkken siteyi gören tek kişi yönetici: unutulmasın diye uyarı --}}
    @if (config('shop.bakim_modu') && auth()->user()?->panelde())
        <div class="bakim-seridi">
            Bakım perdesi AÇIK — ziyaretçiler "Çok yakında" sayfasını görüyor.
            <a href="{{ url('/admin/site-ayarlari') }}">Ayarlardan kapat</a>
        </div>
    @endif

    {{-- Üst şerit: iletişim solda, hızlı bağlantılar sağda --}}
    <div class="ust-serit">
        <div class="kap ust-serit-ic">
            <ul class="ust-serit-sol">
                @if (config('shop.satici.eposta'))
                    <li>
                        <a href="mailto:{{ config('shop.satici.eposta') }}">
                            @include('vitrin.parca.ikon', ['ad' => 'eposta']) {{ config('shop.satici.eposta') }}
                        </a>
                    </li>
                @endif
                @if (config('shop.satici.telefon'))
                    <li>
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('shop.satici.telefon')) }}">
                            @include('vitrin.parca.ikon', ['ad' => 'telefon']) {{ config('shop.satici.telefon') }}
                        </a>
                    </li>
                @endif
                <li>
                    <a href="{{ url('/iletisim') }}">
                        @include('vitrin.parca.ikon', ['ad' => 'konum']) Edirne mağazamız
                    </a>
                </li>
            </ul>

            <ul class="ust-serit-sag">
                @if ((float) config('shop.kargo.ucretsiz_esigi') > 0)
                    <li class="ust-serit-duyuru">
                        {{ number_format((float) config('shop.kargo.ucretsiz_esigi'), 0, ',', '.') }} TL ve üzeri kargo ücretsiz
                    </li>
                @endif
                @foreach ($menuler['ust-serit'] as $bag)
                    <li><a href="{{ $bag['adres'] }}" @if ($bag['yeni_sekme']) target="_blank" rel="noopener" @endif>{{ $bag['etiket'] }}</a></li>
                @endforeach
                <li>
                    <a href="https://instagram.com/{{ config('shop.sosyal.instagram') }}" rel="noopener noreferrer" target="_blank">
                        @include('vitrin.parca.ikon', ['ad' => 'instagram']) Instagram
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <header class="baslik">
        <div class="kap baslik-ic">
            {{--
                Mobil menü. <details> ile: JS çalışmasa da açılır/kapanır.
                Masaüstünde gizli; orada menü bandı var.
            --}}
            <details class="mobil-menu">
                <summary aria-label="Menüyü aç">
                    @include('vitrin.parca.ikon', ['ad' => 'menu'])
                </summary>
                <div class="mobil-menu-panel">
                    <div class="mobil-menu-ust">
                        <span>Menü</span>
                    </div>
                    <nav aria-label="Mobil menü">
                        @foreach ($menuler['ana'] as $bag)
                            <a href="{{ $bag['adres'] }}" @if ($bag['yeni_sekme']) target="_blank" rel="noopener" @endif>{{ $bag['etiket'] }}</a>
                        @endforeach
                        @foreach ($menuKategorileri as $kat)
                            <a href="{{ route('catalog.category', $kat->slug) }}">{{ $kat->name }}</a>
                            @foreach ($kat->children as $alt)
                                <a class="mobil-menu-alt" href="{{ route('catalog.category', $alt->slug) }}">{{ $alt->name }}</a>
                            @endforeach
                        @endforeach
                        @auth
                            <a href="{{ route('account.index') }}">Hesabım</a>
                        @else
                            <a href="{{ route('login') }}">Giriş / Üye Ol</a>
                        @endauth
                    </nav>
                </div>
            </details>

            <a href="{{ url('/') }}" class="baslik-logo" aria-label="{{ config('shop.ad') }} ana sayfa">
                <img src="{{ asset('img/zeys-logo.png') }}" alt="{{ config('shop.ad') }}" width="132" height="97">
            </a>

            <form class="baslik-arama" method="GET" action="{{ route('catalog.search') }}" role="search">
                <label class="gorunmez" for="baslik-q">Ürün ara</label>
                <input type="search" id="baslik-q" name="q" value="{{ request('q') }}"
                       placeholder="Ürün, kategori ya da SKU ara…" autocomplete="off">

                @if ($menuKategorileri->isNotEmpty())
                    <label class="gorunmez" for="baslik-kategori">Kategori</label>
                    <select id="baslik-kategori" name="kategori" class="baslik-arama-kategori">
                        <option value="">Tüm Kategoriler</option>
                        @foreach ($menuKategorileri as $kat)
                            <option value="{{ $kat->slug }}" @selected(request('kategori') === $kat->slug)>{{ $kat->name }}</option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" aria-label="Ara">
                    @include('vitrin.parca.ikon', ['ad' => 'arama'])
                </button>
            </form>

            <div class="baslik-ikonlar">
                <a href="{{ route('catalog.search') }}" class="baslik-ikon baslik-ikon-arama" aria-label="Ara">
                    @include('vitrin.parca.ikon', ['ad' => 'arama'])
                </a>

                @auth
                    <a href="{{ route('account.index') }}" class="baslik-ikon" aria-label="Hesabım">
                        @include('vitrin.parca.ikon', ['ad' => 'kullanici'])
                        <span class="baslik-ikon-metin"><small>Merhaba</small>Hesabım</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="baslik-ikon" aria-label="Giriş yap">
                        @include('vitrin.parca.ikon', ['ad' => 'kullanici'])
                        <span class="baslik-ikon-metin"><small>Giriş yap</small>Hesabım</span>
                    </a>
                @endauth

                <a href="{{ url('/sepet') }}" class="baslik-ikon sepet-bag" aria-label="Sepet, {{ $sepetAdedi }} ürün">
                    <span class="baslik-ikon-kutu">
                        @include('vitrin.parca.ikon', ['ad' => 'sepet'])
                        <span class="sepet-sayi">{{ $sepetAdedi }}</span>
                    </span>
                    <span class="baslik-ikon-metin"><small>Alışveriş</small>Sepet</span>
                </a>
            </div>
        </div>

        {{-- Menü bandı — referanstaki renkli şerit --}}
        <div class="menu-bant">
            <div class="kap menu-bant-ic">
                <div class="kategori-menu">
                    <button type="button" class="kategori-menu-dugme" aria-haspopup="true">
                        <span>Kategoriler</span>
                        @include('vitrin.parca.ikon', ['ad' => 'menu'])
                    </button>

                    <ul class="kategori-menu-liste">
                        @forelse ($menuKategorileri as $kat)
                            <li class="{{ $kat->children->isNotEmpty() ? 'alti-var' : '' }}">
                                <a href="{{ route('catalog.category', $kat->slug) }}">
                                    {{ $kat->name }}
                                    @if ($kat->children->isNotEmpty())
                                        @include('vitrin.parca.ikon', ['ad' => 'ok-sag'])
                                    @endif
                                </a>
                                @if ($kat->children->isNotEmpty())
                                    <ul class="kategori-menu-alt">
                                        @foreach ($kat->children as $alt)
                                            <li><a href="{{ route('catalog.category', $alt->slug) }}">{{ $alt->name }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </li>
                        @empty
                            <li><a href="{{ url('/koleksiyonlar') }}">Tüm ürünler</a></li>
                        @endforelse
                    </ul>
                </div>

                <nav aria-label="Ana menü">
                    <ul class="ana-menu">
                        @foreach ($menuler['ana'] as $bag)
                            @php
                                $yol = parse_url($bag['adres'], PHP_URL_PATH) ?: '/';
                                $aktifMi = rtrim($bag['adres'], '/') === rtrim(url()->full(), '/')
                                    || ($yol !== '/' && ! parse_url($bag['adres'], PHP_URL_QUERY) && request()->is(ltrim($yol, '/') . '*'));
                            @endphp
                            <li><a href="{{ $bag['adres'] }}" @class(['aktif' => $aktifMi])
                                   @if ($bag['yeni_sekme']) target="_blank" rel="noopener" @endif>{{ $bag['etiket'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>

                <p class="menu-bant-not">
                    @include('vitrin.parca.ikon', ['ad' => 'kamyon'])
                    Edirne'den tüm Türkiye'ye
                </p>
            </div>
        </div>
    </header>

    <main id="icerik">
        @yield('icerik')
    </main>

    <footer class="alt">
        {{-- Referanstaki bülten bandı. Bülten altyapısı olmadığı için sahte form
             yerine gerçek kanal: Instagram. --}}
        <div class="alt-bant">
            <div class="kap alt-bant-ic">
                <div class="alt-bant-metin">
                    @include('vitrin.parca.ikon', ['ad' => 'instagram'])
                    <div>
                        <h2>Yeni gelenleri ilk siz görün</h2>
                        <p>Yeni sezon parçaları ve mağaza haberleri Instagram hesabımızda.</p>
                    </div>
                </div>
                <a class="dugme dugme-beyaz" href="https://instagram.com/{{ config('shop.sosyal.instagram') }}"
                   rel="noopener noreferrer" target="_blank">&#64;{{ config('shop.sosyal.instagram') }}</a>
            </div>
        </div>

        <div class="alt-ana">
            <div class="kap alt-izgara">
                <div class="alt-marka">
                    <img src="{{ asset('img/zeys-logo.png') }}" alt="{{ config('shop.ad') }}" width="120" height="88" loading="lazy">
                    <p>Edirne'deki butiğimizde özenle seçtiğimiz kadın giyim parçaları artık çevrim içi de sizinle.</p>
                    <ul class="alt-iletisim">
                        <li>@include('vitrin.parca.ikon', ['ad' => 'konum']) <address>{{ config('shop.satici.adres') }}</address></li>
                        @if (config('shop.satici.telefon'))
                            <li>@include('vitrin.parca.ikon', ['ad' => 'telefon']) <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('shop.satici.telefon')) }}">{{ config('shop.satici.telefon') }}</a></li>
                        @endif
                        @if (config('shop.satici.eposta'))
                            <li>@include('vitrin.parca.ikon', ['ad' => 'eposta']) <a href="mailto:{{ config('shop.satici.eposta') }}">{{ config('shop.satici.eposta') }}</a></li>
                        @endif
                    </ul>
                </div>

                <div>
                    <h3>Alışveriş</h3>
                    <ul>
                        <li><a href="{{ url('/koleksiyonlar') }}">Tüm Ürünler</a></li>
                        @foreach ($menuKategorileri->take(4) as $kat)
                            <li><a href="{{ route('catalog.category', $kat->slug) }}">{{ $kat->name }}</a></li>
                        @endforeach
                        <li><a href="{{ url('/ara') }}">Ürün Ara</a></li>
                    </ul>
                </div>

                <div>
                    <h3>Yardım</h3>
                    <ul>
                        @foreach ($menuler['alt-yardim'] as $bag)
                            <li><a href="{{ $bag['adres'] }}" @if ($bag['yeni_sekme']) target="_blank" rel="noopener" @endif>{{ $bag['etiket'] }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h3>Yasal</h3>
                    <ul>
                        <li><a href="{{ url('/sayfa/on-bilgilendirme') }}">Ön Bilgilendirme Formu</a></li>
                        <li><a href="{{ url('/sayfa/mesafeli-satis') }}">Mesafeli Satış Sözleşmesi</a></li>
                        <li><a href="{{ url('/sayfa/kvkk') }}">KVKK Aydınlatma Metni</a></li>
                        <li><a href="{{ url('/sayfa/cerez') }}">Çerez Politikası</a></li>
                    </ul>
                </div>

                <div>
                    <h3>Hesabım</h3>
                    <ul>
                        <li><a href="{{ route('account.index') }}">Hesabım</a></li>
                        <li><a href="{{ url('/sepet') }}">Sepetim</a></li>
                        @guest
                            <li><a href="{{ route('login') }}">Giriş Yap</a></li>
                            <li><a href="{{ route('register') }}">Üye Ol</a></li>
                        @endguest
                    </ul>
                </div>
            </div>
        </div>

        <div class="alt-kunye">
            <div class="kap alt-kunye-ic">
                <span>&copy; {{ now()->year }} {{ config('shop.ad') }} · Edirne / Türkiye</span>
                <span class="alt-imza">
                    Web Site by
                    <a href="https://maysila.com" rel="noopener" target="_blank">Maysila Digital Agency</a>
                    &amp; <a href="https://www.amesis.com.tr" rel="noopener" target="_blank">Amesis 360</a>
                </span>
                <span class="alt-odeme">
                    @include('vitrin.parca.ikon', ['ad' => 'kalkan'])
                    Kredi ve banka kartıyla güvenli ödeme · PayTR
                </span>
            </div>
        </div>
    </footer>

    {{-- Mobil alt menü — referanstaki sabit alt çubuk (istek listesi yok, o yüzden 4 öğe) --}}
    <nav class="mobil-alt" aria-label="Hızlı erişim">
        <a href="{{ url('/koleksiyonlar') }}">@include('vitrin.parca.ikon', ['ad' => 'magaza'])<span>Mağaza</span></a>
        <a href="{{ route('catalog.search') }}">@include('vitrin.parca.ikon', ['ad' => 'arama'])<span>Ara</span></a>
        <a href="{{ url('/sepet') }}">
            <span class="baslik-ikon-kutu">
                @include('vitrin.parca.ikon', ['ad' => 'sepet'])
                <span class="sepet-sayi">{{ $sepetAdedi }}</span>
            </span>
            <span>Sepet</span>
        </a>
        <a href="{{ route('account.index') }}">@include('vitrin.parca.ikon', ['ad' => 'kullanici'])<span>Hesap</span></a>
    </nav>

    <a href="#ust" class="yukari" aria-label="Sayfa başına dön">@include('vitrin.parca.ikon', ['ad' => 'ok-yukari'])</a>

    @stack('betik')
</body>
</html>
