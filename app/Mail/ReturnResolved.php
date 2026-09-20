<?php

namespace App\Mail;

use App\Models\ReturnRequest;
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

    public function __construct(public ReturnRequest $talep) {}

    public function envelope(): Envelope
    {
        $konu = match ($this->talep->status) {
            'approved' => $this->talep->is_exchange
                ? 'Değişim talebiniz onaylandı'
                : 'İade talebiniz onaylandı',
            'rejected' => 'İade talebiniz hakkında',
            'completed' => $this->talep->is_exchange
                ? 'Değişim ürününüz yola çıktı'
                : 'İade tutarınız gönderildi',
            default => 'İade talebiniz güncellendi',
        };

        return new Envelope(subject: $konu.' — '.$this->talep->number);
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.iade-sonucu',
            with: ['talep' => $this->talep],
        );
    }
}
