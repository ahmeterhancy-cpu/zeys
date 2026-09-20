{{--
    Odeme donus sayfasi. Oturumsuz calisir (bkz. routes/paytr.php),
    bu yuzden burada flash mesaj ya da sepet okunamaz.
--}}
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Siparis {{ $order->number }} — {{ config('shop.ad') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="odeme-donus">
        @if ($durum === 'basarili')
            <h1>Siparisiniz alindi</h1>
            <p>Siparis numaraniz <strong>{{ $order->number }}</strong>.</p>
            <p>Onay e-postasi {{ $order->customer_email }} adresine gonderildi.</p>
        @elseif ($durum === 'isleniyor')
            <h1>Odemeniz isleniyor</h1>
            <p>
                Siparis numaraniz <strong>{{ $order->number }}</strong>.
                Bankadan onay bekleniyor; bu birkac saniye surebilir.
                Sayfayi yenileyerek durumu gorebilirsiniz.
            </p>
        @else
            <h1>Odeme tamamlanamadi</h1>
            <p>
                <strong>{{ $order->number }}</strong> numarali siparisin odemesi alinamadi.
                Kartinizdan para cekilmedi.
            </p>
        @endif

        <p><a href="{{ url('/') }}">Alisverise devam et</a></p>
    </main>
</body>
</html>
