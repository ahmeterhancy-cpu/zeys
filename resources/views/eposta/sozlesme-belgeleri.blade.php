@extends('eposta.duzen')

@section('konu', 'Sözleşme belgeleriniz — ' . $order->number)

@section('icerik')
    <h1 style="margin:0 0 6px; font-size:26px; font-weight:normal; color:#1c1a15;">
        Sözleşme belgeleriniz
    </h1>

    <p style="margin:0 0 20px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Sipariş <strong style="color:#1c1a15;">{{ $order->number }}</strong> için sipariş
        sırasında onayladığınız Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi
        aşağıdadır. Bu e-postayı saklamanızı öneririz.
    </p>

    {{-- Taraflar ve siparişe özgü bilgiler --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#554f44;">
        <tr>
            <td style="padding:14px 16px; background:#faf6ea; border:1px solid #e6dfd2;">
                <strong style="color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase;">Satıcı</strong><br>
                {{ config('shop.satici.unvan') ?: config('shop.ad') }}<br>
                {{ config('shop.satici.adres') }}<br>
                @if (config('shop.satici.telefon')) {{ config('shop.satici.telefon') }} · @endif
                {{ config('shop.satici.eposta') }}
                @if (config('shop.satici.mersis'))<br>MERSİS: {{ config('shop.satici.mersis') }}@endif
                <br><br>
                <strong style="color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase;">Alıcı</strong><br>
                {{ $order->customer_name }} · {{ $order->customer_email }} · {{ $order->customer_phone }}
                <br><br>
                <strong style="color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase;">Sipariş ve onay</strong><br>
                Sipariş tarihi: {{ \App\Support\Tarih::goster($order->created_at) }}<br>
                Sözleşme onayı: {{ \App\Support\Tarih::goster($order->contract_accepted_at) }}
                @if ($order->contract_ip) · IP {{ $order->contract_ip }} @endif
            </td>
        </tr>
    </table>

    @include('eposta.parca.siparis-tablosu', ['order' => $order])

    @foreach ([$onBilgi, $sozlesme] as $belge)
        @if ($belge)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:34px 0 0;">
                <tr>
                    <td style="padding:0 0 10px; border-bottom:1px solid #bc9c51;">
                        <span style="font-size:22px; color:#1c1a15;">{{ $belge->title }}</span><br>
                        <span style="font-family:Arial,Helvetica,sans-serif; font-size:11px; color:#8a8275;">
                            Sürüm {{ $belge->version }}
                        </span>
                    </td>
                </tr>
                <tr>
                    {{-- Yöneticinin panelden girdiği güvenilir HTML --}}
                    <td style="padding:14px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7; color:#554f44;">
                        {!! $belge->body !!}
                    </td>
                </tr>
            </table>
        @endif
    @endforeach

    @if (! $onBilgi || ! $sozlesme)
        <p style="margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#8a8275;">
            Belgelerin güncel hâline sitemizdeki yasal sayfalardan ulaşabilirsiniz.
        </p>
    @endif
@endsection
