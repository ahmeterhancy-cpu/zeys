{{-- Sipariş kalemleri ve tutarlar. Satır içi stil: bkz. eposta/duzen.blade.php --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="border-collapse:collapse; margin:22px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:14px;">

    @foreach ($order->items as $kalem)
        <tr>
            <td style="padding:11px 0; border-bottom:1px solid #e6dfd2; color:#1c1a15;">
                {{ $kalem->name }}
                @if ($kalem->variant_label)
                    <br><span style="color:#8a8275; font-size:12px;">{{ $kalem->variant_label }}</span>
                @endif
                <br><span style="color:#8a8275; font-size:11px;">{{ $kalem->sku }}</span>
            </td>
            <td align="center" style="padding:11px 10px; border-bottom:1px solid #e6dfd2; color:#554f44; white-space:nowrap;">
                × {{ $kalem->quantity }}
            </td>
            <td align="right" style="padding:11px 0; border-bottom:1px solid #e6dfd2; color:#1c1a15; white-space:nowrap;">
                {{ number_format((float) $kalem->line_total, 2, ',', '.') }} TL
            </td>
        </tr>
    @endforeach

    <tr>
        <td colspan="2" style="padding:11px 0 4px; color:#554f44;">Ara toplam</td>
        <td align="right" style="padding:11px 0 4px; color:#554f44; white-space:nowrap;">
            {{ number_format((float) $order->subtotal, 2, ',', '.') }} TL
        </td>
    </tr>

    @if ((float) $order->discount_total > 0)
        <tr>
            <td colspan="2" style="padding:4px 0; color:#2f6f4a;">
                İndirim{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : '' }}
            </td>
            <td align="right" style="padding:4px 0; color:#2f6f4a; white-space:nowrap;">
                -{{ number_format((float) $order->discount_total, 2, ',', '.') }} TL
            </td>
        </tr>
    @endif

    <tr>
        <td colspan="2" style="padding:4px 0; color:#554f44;">Kargo</td>
        <td align="right" style="padding:4px 0; color:#554f44; white-space:nowrap;">
            @if ((float) $order->shipping_total > 0)
                {{ number_format((float) $order->shipping_total, 2, ',', '.') }} TL
            @else
                Ücretsiz
            @endif
        </td>
    </tr>

    <tr>
        <td colspan="2" style="padding:13px 0 0; border-top:1px solid #cdc2ad; color:#1c1a15; font-weight:bold;">
            Toplam
        </td>
        <td align="right" style="padding:13px 0 0; border-top:1px solid #cdc2ad; color:#1c1a15; font-weight:bold; white-space:nowrap;">
            {{ number_format((float) $order->grand_total, 2, ',', '.') }} TL
        </td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="margin:26px 0 0; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.7;">
    <tr>
        <td style="color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase; padding-bottom:8px;">
            Teslimat adresi
        </td>
    </tr>
    <tr>
        <td style="color:#554f44;">
            @php($adres = $order->shipping_address ?? [])
            {{ $adres['name'] ?? '' }}<br>
            {{ trim(($adres['line1'] ?? '').' '.($adres['line2'] ?? '')) }}<br>
            {{ ($adres['district'] ?? '').' / '.($adres['city'] ?? '') }}
            @if (! empty($adres['postal_code']))
                <br>{{ $adres['postal_code'] }}
            @endif
            @if (! empty($adres['phone']))
                <br>{{ $adres['phone'] }}
            @endif
        </td>
    </tr>

    @php($fatura = $order->billing_address ?? [])
    @if (! empty($fatura))
        <tr>
            <td style="color:#6e5a33; font-size:11px; letter-spacing:2px; text-transform:uppercase; padding:18px 0 8px;">
                Fatura
            </td>
        </tr>
        <tr>
            <td style="color:#554f44;">
                @if (($fatura['invoice_type'] ?? null) === 'corporate')
                    {{ $fatura['company_name'] ?? '' }}<br>
                    {{ $fatura['tax_office'] ?? '' }} V.D. · {{ $fatura['tax_number'] ?? '' }}<br>
                @else
                    {{ $fatura['name'] ?? '' }} (bireysel)<br>
                    {{-- TCKN e-postada maskeli: e-posta güvenli bir kanal değil --}}
                    @if (! empty($fatura['tax_number']))
                        T.C. kimlik no: •••••••{{ substr($fatura['tax_number'], -4) }}<br>
                    @endif
                @endif
                {{ trim(($fatura['line1'] ?? '').' '.($fatura['line2'] ?? '')) }}<br>
                {{ ($fatura['district'] ?? '').' / '.($fatura['city'] ?? '') }}
            </td>
        </tr>
    @endif
</table>
