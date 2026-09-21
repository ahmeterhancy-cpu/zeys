@extends('eposta.duzen')

@section('konu', $metin['konu'])

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        {{ $metin['baslik'] }}
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Sipariş numaranız
        <strong style="color:#1c1a15;">{{ $order->number }}</strong>
    </p>

    @if (filled($metin['metin']))
        <p style="margin:0 0 18px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44; line-height:1.7;">{!! nl2br(e($metin['metin'])) !!}</p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 6px;">
        <tr>
            <td style="padding:18px 20px; background:#faf6ea; border:1px solid #e6dfd2; font-family:Arial,Helvetica,sans-serif;">
                <p style="margin:0 0 6px; color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase;">
                    Kargo bilgisi
                </p>
                <p style="margin:0; font-size:15px; color:#1c1a15;">
                    {{ $order->shipping_carrier ?: 'Kargo firması' }}
                    @if ($order->tracking_number)
                        <br>
                        <span style="color:#554f44; font-size:13px;">Takip numarası</span>
                        <strong style="letter-spacing:1px;">{{ $order->tracking_number }}</strong>
                    @endif
                </p>
            </td>
        </tr>
    </table>

    @if (filled($metin['not']))
        <p style="margin:18px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#8a8275;">
            {!! nl2br(e($metin['not'])) !!}
        </p>
    @endif

    @include('eposta.parca.siparis-dugmesi', ['order' => $order])

    @include('eposta.parca.siparis-tablosu', ['order' => $order])
@endsection
