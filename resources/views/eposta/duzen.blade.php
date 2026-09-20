{{--
    E-posta düzeni.

    TABLO tabanlı ve stiller SATIR İÇİ. Outlook ve birçok e-posta
    istemcisi <style> bloğunu, flexbox'ı ve CSS değişkenlerini
    desteklemiyor — vitrindeki jeton sistemi burada kullanılamaz,
    renkler elle yazılır.

    Renkler logodan ölçülen paletin aynısı:
      mürekkep #1c1a15 · altın metin #6e5a33 · altın çizgi #bc9c51
      kâğıt #ffffff · kırık beyaz #faf7f0
--}}
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('konu', config('shop.ad'))</title>
</head>
<body style="margin:0; padding:0; background:#faf7f0;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#faf7f0;">
    <tr>
        <td align="center" style="padding:28px 16px;">

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="max-width:600px; background:#ffffff; border:1px solid #e6dfd2;">

                {{-- Başlık --}}
                <tr>
                    <td align="center" style="padding:34px 28px 24px; border-bottom:1px solid #e6dfd2;">
                        <a href="{{ url('/') }}" style="text-decoration:none;">
                            <img src="{{ url('img/zeys-logo.png') }}"
                                 alt="{{ config('shop.ad') }}"
                                 width="168"
                                 style="display:block; width:168px; max-width:60%; height:auto; border:0;">
                        </a>
                    </td>
                </tr>

                {{-- İçerik --}}
                <tr>
                    <td style="padding:30px 28px 34px; font-family:Georgia,'Times New Roman',serif; color:#1c1a15; font-size:16px; line-height:1.65;">
                        @yield('icerik')
                    </td>
                </tr>

                {{-- Alt bilgi --}}
                <tr>
                    <td style="padding:22px 28px 26px; background:#faf7f0; border-top:1px solid #e6dfd2; font-family:Arial,Helvetica,sans-serif; font-size:12px; line-height:1.7; color:#8a8275;">
                        <p style="margin:0 0 8px; color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase;">
                            {{ config('shop.ad') }}
                        </p>
                        <p style="margin:0 0 6px;">{{ config('shop.satici.adres') }}</p>
                        @if (config('shop.satici.telefon'))
                            <p style="margin:0 0 6px;">{{ config('shop.satici.telefon') }}</p>
                        @endif
                        <p style="margin:0;">
                            <a href="https://instagram.com/{{ config('shop.sosyal.instagram') }}"
                               style="color:#6e5a33; text-decoration:none;">
                                &#64;{{ config('shop.sosyal.instagram') }}
                            </a>
                        </p>
                    </td>
                </tr>
            </table>

        </td>
    </tr>
</table>

</body>
</html>
