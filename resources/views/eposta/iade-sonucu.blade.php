@extends('eposta.duzen')

@section('konu', 'İade talebiniz — ' . $talep->number)

@section('icerik')
    @php($order = $talep->order)

    <h1 style="margin:0 0 6px; font-size:28px; font-weight:normal; color:#1c1a15;">
        @if ($talep->status === 'approved')
            {{ $talep->is_exchange ? 'Değişim talebiniz onaylandı' : 'İade talebiniz onaylandı' }}
        @elseif ($talep->status === 'rejected')
            İade talebiniz hakkında
        @elseif ($talep->status === 'completed')
            {{ $talep->is_exchange ? 'Değişim ürününüz yola çıktı' : 'İade tutarınız gönderildi' }}
        @else
            Talebiniz güncellendi
        @endif
    </h1>

    <p style="margin:0 0 22px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
        Talep numarası <strong style="color:#1c1a15;">{{ $talep->number }}</strong>
        · Sipariş {{ $order->number }}
    </p>

    @if ($talep->status === 'approved' && ! $talep->is_exchange)
        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
            Ürününüz tarafımıza ulaştı ve incelendi. İade tutarı
            <strong style="color:#1c1a15;">{{ number_format((float) $talep->refund_amount, 2, ',', '.') }} TL</strong>
            olarak onaylandı; ödemeyi yaptığınız karta
            <strong>14 gün</strong> içinde iade edilecek. Bankaya göre
            hesabınıza yansıması birkaç gün sürebilir.
        </p>

    @elseif ($talep->status === 'approved' && $talep->is_exchange)
        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
            Ürününüz tarafımıza ulaştı. Talep ettiğiniz beden hazırlanıyor;
            kargoya verildiğinde takip numarasını ileteceğiz. Değişimde
            ücret iadesi yapılmaz.
        </p>

    @elseif ($talep->status === 'rejected')
        <p style="margin:0 0 16px; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
            Talebinizi inceledik ancak onaylayamadık. Gerekçe aşağıda.
            Sorunuz olursa bize yazabilirsiniz.
        </p>

        @if ($talep->admin_note)
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding:16px 18px; background:#fdf3f3; border-left:2px solid #a32b2b; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.7; color:#7d2020;">
                        {{ $talep->admin_note }}
                    </td>
                </tr>
            </table>
        @endif

    @elseif ($talep->status === 'completed' && $talep->is_exchange)
        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
            Değişim ürününüz kargoya verildi.
            @if ($talep->exchange_tracking_number)
                Takip numarası
                <strong style="letter-spacing:1px; color:#1c1a15;">{{ $talep->exchange_tracking_number }}</strong>
                ({{ $talep->exchange_carrier }}).
            @endif
        </p>

    @elseif ($talep->status === 'completed')
        <p style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#554f44;">
            İade tutarı
            <strong style="color:#1c1a15;">{{ number_format((float) $talep->refund_amount, 2, ',', '.') }} TL</strong>
            gönderildi. Bankaya göre hesabınıza yansıması birkaç gün sürebilir.
        </p>
    @endif

    {{-- İade edilen kalemler --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="border-collapse:collapse; margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px;">
        <tr>
            <td colspan="2" style="color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase; padding-bottom:10px;">
                Talebe konu ürünler
            </td>
        </tr>
        @foreach ($talep->items as $kalem)
            <tr>
                <td style="padding:10px 0; border-bottom:1px solid #e6dfd2; color:#1c1a15;">
                    {{ $kalem->orderItem->name }}
                    @if ($kalem->orderItem->variant_label)
                        <br><span style="color:#8a8275; font-size:12px;">{{ $kalem->orderItem->variant_label }}</span>
                    @endif
                    @if ($kalem->exchangeVariant)
                        <br><span style="color:#6e5a33; font-size:12px;">
                            Değişim → {{ $kalem->exchangeVariant->label }}
                        </span>
                    @endif
                </td>
                <td align="right" style="padding:10px 0; border-bottom:1px solid #e6dfd2; color:#554f44; white-space:nowrap;">
                    × {{ $kalem->quantity }}
                </td>
            </tr>
        @endforeach
    </table>
@endsection
