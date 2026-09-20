<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Siparişiniz kargoya verildi — '.$this->order->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.siparis-kargoda',
            with: ['order' => $this->order],
        );
    }
}
