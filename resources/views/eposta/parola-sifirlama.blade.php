@extends('eposta.duzen')

@section('konu', 'Parola sifirlama')

@section('icerik')
    <h1 style="margin:0 0 16px; font-size:28px; font-weight:normal; color:#1c1a15;">
        Parola sıfırlama
    </h1>

    <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Hesabınız için parola sıfırlama istendi. Yeni parola belirlemek için
        aşağıdaki düğmeye tıklayın. Bağlantı {{ $dakika }} dakika geçerlidir
        ve yalnızca bir kez kullanılabilir.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;">
        <tr>
            <td style="background:#1c1a15;">
                <a href="{{ $baglanti }}"
                   style="display:inline-block; padding:15px 30px; color:#ffffff; text-decoration:none; font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:2px; text-transform:uppercase;">
                    Yeni parola belirle
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8275;">
        Bu isteği siz yapmadıysanız bu e-postayı yok sayın; parolanız değişmez.
    </p>
@endsection
