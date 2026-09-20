<?php

namespace App\Mail;

use App\Models\Order;
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

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Siparişiniz alındı — '.$this->order->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.siparis-alindi',
            with: ['order' => $this->order],
        );
    }
}
