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
<body>
    <a href="#icerik" class="gorunmez">İçeriğe atla</a>

    @if ((float) config('shop.kargo.ucretsiz_esigi') > 0)
        <div class="duyuru">
            {{ number_format((float) config('shop.kargo.ucretsiz_esigi'), 0, ',', '.') }} TL ve üzeri kargo ücretsiz
        </div>
    @endif

    <header class="ust">
        <div class="kap ust-ic">
            <nav aria-label="Ana menü">
                <ul class="ust-menu">
                    <li><a href="{{ url('/') }}">Ana Sayfa</a></li>
                    <li><a href="{{ url('/koleksiyonlar') }}">Koleksiyonlar</a></li>
                    <li><a href="{{ url('/ara') }}">Ara</a></li>
                    <li><a href="{{ url('/iletisim') }}">İletişim</a></li>
                </ul>
            </nav>

            <a href="{{ url('/') }}" class="ust-marka" aria-label="{{ config('shop.ad') }} ana sayfa">
                <img src="{{ asset('img/zeys-logo.png') }}"
                     alt="{{ config('shop.ad') }}" width="132" height="97">
            </a>

            <div class="ust-sag">
                <a href="{{ url('/sepet') }}" class="sepet-bag">
                    Sepet
                    @if ($sepetAdedi > 0)
                        <span class="sepet-sayi">{{ $sepetAdedi }}</span>
                    @endif
                </a>
            </div>
        </div>
    </header>

    <main id="icerik">
        @yield('icerik')
    </main>

    <footer class="alt">
        <div class="kap">
            <div class="alt-izgara">
                <div>
                    <h3>{{ config('shop.ad') }}</h3>
                    <address>{{ config('shop.satici.adres') }}</address>
                    <p style="margin-top:14px">
                        <a href="https://instagram.com/{{ config('shop.sosyal.instagram') }}"
                           rel="noopener noreferrer" target="_blank">
                            &#64;{{ config('shop.sosyal.instagram') }}
                        </a>
                    </p>
                </div>

                <div>
                    <h3>Alışveriş</h3>
                    <ul>
                        <li><a href="{{ url('/koleksiyonlar') }}">Koleksiyonlar</a></li>
                        <li><a href="{{ url('/sepet') }}">Sepetim</a></li>
                        <li><a href="{{ url('/siparis-sorgula') }}">Sipariş Sorgula</a></li>
                    </ul>
                </div>

                <div>
                    <h3>Yardım</h3>
                    <ul>
                        <li><a href="{{ url('/sayfa/iade-degisim') }}">İade ve Değişim</a></li>
                        <li><a href="{{ url('/sayfa/on-bilgilendirme') }}">Ön Bilgilendirme Formu</a></li>
                        <li><a href="{{ url('/sayfa/mesafeli-satis') }}">Mesafeli Satış Sözleşmesi</a></li>
                    </ul>
                </div>

                <div>
                    <h3>Yasal</h3>
                    <ul>
                        <li><a href="{{ url('/sayfa/kvkk') }}">KVKK Aydınlatma Metni</a></li>
                        <li><a href="{{ url('/sayfa/cerez') }}">Çerez Politikası</a></li>
                    </ul>
                </div>
            </div>

            <div class="alt-kunye">
                <span>&copy; {{ now()->year }} {{ config('shop.ad') }}</span>
                <span>Edirne / Türkiye</span>
            </div>
        </div>
    </footer>
    @stack('betik')
</body>
</html>
