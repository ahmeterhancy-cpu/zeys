<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Fatura kaydı ve muhasebe dökümü.
 *
 * e-Arşiv entegrasyonu YOK: fatura muhasebe programında / GİB portalında
 * kesilir. Burada numarası, tarihi ve PDF'i siparişe bağlanır, müşteriye
 * gönderilir. Bir entegratör (Paraşüt, Logo, özel entegratör…) seçilirse
 * kesme adımı buraya eklenir; kayıt ve gönderim aynı kalır.
 */
class Faturalar
{
    public function __construct(private Notifier $notifier) {}

    /**
     * @param  string|null  $pdf  gizli diskteki (local) yol; null = PDF'e dokunma
     */
    public function kaydet(Order $order, string $numara, string $tarih, ?string $pdf, bool $gonder): bool
    {
        $eskiPdf = $order->invoice_pdf;

        $order->forceFill([
            'invoice_number' => trim($numara),
            'invoice_date' => CarbonImmutable::parse($tarih)->toDateString(),
            'invoice_pdf' => $pdf ?? $order->invoice_pdf,
        ])->save();

        // Yeni PDF yüklendiyse eskisi silinir (gizli diskte yetim dosya kalmasın)
        if ($pdf && $eskiPdf && $eskiPdf !== $pdf) {
            Storage::disk('local')->delete($eskiPdf);
        }

        return $gonder && $order->invoice_pdf
            ? $this->notifier->invoice($order->fresh())
            : false;
    }

    /**
     * Muhasebeye verilecek döküm: ödeme tarihi aralığındaki ödenmiş siparişler.
     * Türkçe Excel biçimi (; ayraç, ondalık virgül, BOM).
     */
    public function muhasebeCsv(string $baslangic, string $bitis): string
    {
        $bas = CarbonImmutable::parse($baslangic)->startOfDay();
        $son = CarbonImmutable::parse($bitis)->endOfDay();
        $para = fn ($t) => number_format((float) $t, 2, ',', '');

        $akis = fopen('php://temp', 'r+');
        fwrite($akis, "\xEF\xBB\xBF");
        $yaz = fn (array $s) => fputcsv($akis, $s, ';', '"', '');

        $yaz([
            'Sipariş no', 'Ödeme tarihi', 'Durum', 'Fatura türü', 'Ad / Unvan', 'TCKN / VKN', 'Vergi dairesi',
            'Fatura adresi', 'İl', 'E-posta', 'Telefon',
            'Ara toplam', 'İndirim', 'Kargo', 'Toplam (KDV dahil)', 'İade edilen',
            'Fatura no', 'Fatura tarihi',
        ]);

        Order::query()
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$bas, $son])
            ->orderBy('paid_at')
            ->each(function (Order $o) use ($yaz, $para) {
                $f = $o->invoice_address ?? [];
                $b = $o->billing_address ?? [];
                $kurumsal = ($b['invoice_type'] ?? null) === 'corporate';

                $yaz([
                    $o->number,
                    $o->paid_at?->format('d.m.Y H:i'),
                    $o->status_label,
                    $kurumsal ? 'Kurumsal' : 'Bireysel',
                    $kurumsal ? ($b['company_name'] ?? $f['name'] ?? '') : ($f['name'] ?? $o->customer_name),
                    $b['tax_number'] ?? '',
                    $b['tax_office'] ?? '',
                    trim(($f['line1'] ?? '').' '.($f['line2'] ?? '').' '.($f['district'] ?? '')),
                    $f['city'] ?? '',
                    $o->customer_email,
                    $o->customer_phone,
                    $para($o->subtotal),
                    $para($o->discount_total),
                    $para($o->shipping_total),
                    $para($o->grand_total),
                    $para($o->refunded_total),
                    $o->invoice_number,
                    $o->invoice_date?->format('d.m.Y'),
                ]);
            });

        rewind($akis);
        $icerik = stream_get_contents($akis);
        fclose($akis);

        return $icerik;
    }
}
