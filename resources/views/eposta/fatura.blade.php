@extends('eposta.duzen')

@section('konu', $metin['konu'])

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        {{ $metin['baslik'] }}
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Fatura no <strong style="color:#1c1a15;">{{ $order->invoice_number }}</strong>
        @if ($order->invoice_date)
            · {{ $order->invoice_date->format('d.m.Y') }}
        @endif
    </p>

    @if (filled($metin['metin']))
        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.7; color:#554f44;">
            {!! nl2br(e($metin['metin'])) !!}
        </p>
    @endif

    @include('eposta.parca.siparis-dugmesi', ['order' => $order])

    @if (filled($metin['not']))
        <p style="margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8275;">
            {!! nl2br(e($metin['not'])) !!}
        </p>
    @endif
@endsection
