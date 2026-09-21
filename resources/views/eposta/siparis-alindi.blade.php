@extends('eposta.duzen')

@section('konu', $metin['konu'])

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        {{ $metin['baslik'] }}
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Sipariş numaranız
        <strong style="color:#1c1a15;">{{ $order->number }}</strong>
        · {{ $order->created_at->format('d.m.Y H:i') }}
    </p>

    <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        {!! nl2br(e($metin['metin'])) !!}
    </p>

    @include('eposta.parca.siparis-tablosu', ['order' => $order])

    @include('eposta.parca.siparis-dugmesi', ['order' => $order])

    @if (filled($metin['not']))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:30px 0 0;">
            <tr>
                <td style="padding:16px 18px; background:#faf6ea; border-left:2px solid #bc9c51; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#554f44;">
                    {!! nl2br(e($metin['not'])) !!}
                </td>
            </tr>
        </table>
    @endif
@endsection
