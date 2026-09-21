@extends('eposta.duzen')

@section('konu', $metin['konu'])

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        {{ $metin['baslik'] }}
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        <strong style="color:#1c1a15;">{{ $variant->product->name }}</strong>
        @if ($variant->label)
            · {{ $variant->label }}
        @endif
    </p>

    <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        {!! nl2br(e($metin['metin'])) !!}
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

    @if (filled($metin['not']))
        <p style="margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8275;">
            {!! nl2br(e($metin['not'])) !!}
        </p>
    @endif
@endsection
