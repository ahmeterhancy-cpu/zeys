<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\EpostaMetni;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sipariş onayı — ödeme doğrulandıktan sonra gider.
 *
 * Sipariş verildiğinde DEĞİL, ödeme onaylandığında gönderilir; aksi
 * hâlde ödemesi düşen siparişler için de "siparişiniz alındı" giderdi.
 */
class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    /** Panelden değiştirilebilen metinler (bkz. App\Support\EpostaMetni) */
    public array $metin;

    public function __construct(public Order $order)
    {
        $this->metin = EpostaMetni::al('siparis-alindi', [
            'ad' => $order->customer_name,
            'siparis_no' => $order->number,
            'cayma_gun' => config('shop.cayma_hakki_gun'),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->metin['konu']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.siparis-alindi',
            with: ['order' => $this->order],
        );
    }
}
