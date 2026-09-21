<?php

namespace App\Mail;

use App\Models\ReturnRequest;
use App\Support\EpostaMetni;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * İade / değişim sonucu.
 *
 * Hem onay hem ret için kullanılır; metin talebin durumuna göre değişir.
 * Ret gerekçesi müşteriye AYNEN iletilir — panelde yazılan notun
 * müşteriye okunabilir olması bu yüzden önemli.
 */
class ReturnResolved extends Mailable
{
    use Queueable, SerializesModels;

    /** Panelden değiştirilebilen metinler (bkz. App\Support\EpostaMetni); null = genel güncelleme */
    public ?array $metin;

    public function __construct(public ReturnRequest $talep)
    {
        $anahtar = match ($talep->status) {
            'approved' => $talep->is_exchange ? 'degisim-onay' : 'iade-onay',
            'rejected' => 'iade-ret',
            'completed' => $talep->is_exchange ? 'degisim-tamam' : 'iade-tamam',
            default => null,
        };

        $this->metin = $anahtar ? EpostaMetni::al($anahtar, [
            'ad' => $talep->order?->customer_name,
            'talep_no' => $talep->number,
            'siparis_no' => $talep->order?->number,
            'tutar' => number_format((float) $talep->refund_amount, 2, ',', '.'),
            'kargo_firma' => $talep->exchange_carrier,
            'takip_no' => $talep->exchange_tracking_number,
        ]) : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->metin['konu'] ?? 'İade talebiniz güncellendi — '.$this->talep->number);
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.iade-sonucu',
            with: ['talep' => $this->talep],
        );
    }
}
