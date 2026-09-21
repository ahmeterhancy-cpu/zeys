{{--
    Ödeme dönüş sayfası. Oturumsuz çalışır (bkz. routes/paytr.php),
    bu yüzden burada flash mesaj ya da sepet okunamaz; vitrin düzeni
    (sepet sayacı oturuma bakıyor) kullanılamaz.
--}}
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sipariş {{ $order->number }} — {{ config('shop.ad') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="odeme-donus">
        <a href="{{ url('/') }}"><img src="{{ asset('img/zeys-logo.png') }}" alt="{{ config('shop.ad') }}" width="110" height="81" style="margin:0 auto 26px"></a>

        @if ($durum === 'basarili')
            <h1>Siparişiniz alındı</h1>
            <p>Sipariş numaranız <strong>{{ $order->number }}</strong>.</p>
            <p>Onay e-postası {{ $order->customer_email }} adresine gönderildi.</p>
        @elseif ($durum === 'isleniyor')
            <h1>Ödemeniz işleniyor</h1>
            <p>
                Sipariş numaranız <strong>{{ $order->number }}</strong>.
                Bankadan onay bekleniyor; bu birkaç saniye sürebilir.
                Sayfayı yenileyerek durumu görebilirsiniz.
            </p>
        @else
            <h1>Ödeme tamamlanamadı</h1>
            <p>
                <strong>{{ $order->number }}</strong> numaralı siparişin ödemesi alınamadı.
                Kartınızdan para çekilmedi.
            </p>
        @endif

        <p style="margin-top:26px"><a class="dugme" href="{{ url('/') }}">Alışverişe devam et</a></p>
    </main>
</body>
</html>
