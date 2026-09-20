@extends('eposta.duzen')

@section('konu', 'Siparişiniz alındı — ' . $order->number)

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        Siparişiniz alındı
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Sipariş numaranız
        <strong style="color:#1c1a15;">{{ $order->number }}</strong>
        · {{ $order->created_at->format('d.m.Y H:i') }}
    </p>

    <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Merhaba {{ $order->customer_name }}, siparişinizi aldık ve hazırlamaya
        başlıyoruz. Kargoya verildiğinde takip numarasını size ayrıca ileteceğiz.
    </p>

    @include('eposta.parca.siparis-tablosu', ['order' => $order])

    @include('eposta.parca.siparis-dugmesi', ['order' => $order])

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:30px 0 0;">
        <tr>
            <td style="padding:16px 18px; background:#faf6ea; border-left:2px solid #bc9c51; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#554f44;">
                Ürünü teslim aldıktan sonra
                <strong style="color:#1c1a15;">{{ config('shop.cayma_hakki_gun') }} gün</strong>
                içinde iade ya da değişim hakkınız var. Beden tutmazsa
                aynı modelin başka bedeniyle değiştirebilirsiniz.
            </td>
        </tr>
    </table>
@endsection
