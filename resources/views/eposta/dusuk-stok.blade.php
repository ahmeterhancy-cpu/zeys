@extends('eposta.duzen')

@section('konu', $variant->stock < 1 ? 'Ürün tükendi' : 'Stok azaldı')

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        {{ $variant->stock < 1 ? 'Ürün tükendi' : 'Stok azaldı' }}
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        <strong style="color:#1c1a15;">{{ $variant->product->name }}</strong>
        @if ($variant->label)
            · {{ $variant->label }}
        @endif
        <br>
        <span style="color:#8a8275;">SKU {{ $variant->sku }}</span>
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           style="border-top:1px solid #e6dfd0; border-bottom:1px solid #e6dfd0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#1c1a15;">
        <tr>
            <td style="padding:12px 0;">Raftaki adet</td>
            <td style="padding:12px 0; text-align:right;"><strong>{{ $variant->stock }}</strong></td>
        </tr>
        @if ($variant->reserved > 0)
            <tr>
                <td style="padding:0 0 12px; color:#554f44;">Ödemesi beklenen siparişte ayrılmış</td>
                <td style="padding:0 0 12px; text-align:right; color:#554f44;">{{ $variant->reserved }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding:0 0 12px; color:#554f44;">Uyarı eşiği</td>
            <td style="padding:0 0 12px; text-align:right; color:#554f44;">{{ (int) config('shop.dusuk_stok_esigi') }}</td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;">
        <tr>
            <td style="background:#1c1a15;">
                <a href="{{ url('/admin/products/'.$variant->product_id.'/edit') }}"
                   style="display:inline-block; padding:15px 30px; color:#ffffff; text-decoration:none; font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:2px; text-transform:uppercase;">
                    Panelde aç
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8275;">
        Bu uyarı stok eşiğin ALTINA İNDİĞİ anda bir kez gönderilir. Eşik ve
        adres panelde Site Ayarları'ndan değiştirilir.
    </p>
@endsection
