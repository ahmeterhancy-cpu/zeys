@extends('eposta.duzen')

@section('konu', 'Beklediğiniz ürün stokta')

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        Beklediğiniz ürün stokta
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        <strong style="color:#1c1a15;">{{ $variant->product->name }}</strong>
        @if ($variant->label)
            · {{ $variant->label }}
        @endif
    </p>

    <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Haber verilmesini istediğiniz beden yeniden satışta. Sınırlı sayıda
        olabileceği için beklemeden bakmanızı öneririz.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;">
        <tr>
            <td style="background:#1c1a15;">
                <a href="{{ url('/urun/' . $variant->product->slug) }}"
                   style="display:inline-block; padding:15px 30px; color:#ffffff; text-decoration:none; font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:2px; text-transform:uppercase;">
                    Ürüne git
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8275;">
        Bu bildirimi, ürün sayfasında "haber ver" dediğiniz için aldınız.
        Başka bir bildirim gönderilmeyecek.
    </p>
@endsection
