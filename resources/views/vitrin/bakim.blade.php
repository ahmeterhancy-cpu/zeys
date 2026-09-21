<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ config('shop.ad') }} — Yakında</title>
    <link rel="icon" href="{{ asset('img/zeys-logo-sm.png') }}" type="image/png">
    @vite(['resources/css/app.css'])
</head>
<body>
    {{-- Bakım perdesi: menü, sepet ya da oturuma bağlı hiçbir şey yok --}}
    <main class="bakim">
        <img src="{{ asset('img/zeys-logo.png') }}" alt="{{ config('shop.ad') }}" width="300" height="221">

        <h1>Çok yakında</h1>

        <p>
            Çevrim içi mağazamızı hazırlıyoruz. Bu sürede Edirne'deki butiğimizde
            sizi bekliyoruz.
        </p>

        <address>{{ config('shop.satici.adres') }}</address>

        <p>
            <a href="https://instagram.com/{{ config('shop.sosyal.instagram') }}" rel="noopener noreferrer">
                &#64;{{ config('shop.sosyal.instagram') }}
            </a>
        </p>
    </main>
</body>
</html>
