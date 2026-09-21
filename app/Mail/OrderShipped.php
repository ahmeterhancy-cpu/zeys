<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\EpostaMetni;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    /** Panelden değiştirilebilen metinler (bkz. App\Support\EpostaMetni) */
    public array $metin;

    public function __construct(public Order $order)
    {
        $this->metin = EpostaMetni::al('siparis-kargoda', [
            'ad' => $order->customer_name,
            'siparis_no' => $order->number,
            'kargo_firma' => $order->shipping_carrier,
            'takip_no' => $order->tracking_number,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->metin['konu']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.siparis-kargoda',
            with: ['order' => $this->order],
        );
    }
}
