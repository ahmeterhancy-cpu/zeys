<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\EpostaMetni;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Müşteriye fatura PDF'i. Metin panelden değişir (Ayarlar → E-posta Metinleri). */
class FaturaGonderimi extends Mailable
{
    use Queueable, SerializesModels;

    public array $metin;

    public function __construct(public Order $order)
    {
        $this->metin = EpostaMetni::al('fatura', [
            'ad' => $order->customer_name,
            'siparis_no' => $order->number,
            'fatura_no' => $order->invoice_number,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->metin['konu']);
    }

    public function content(): Content
    {
        return new Content(view: 'eposta.fatura');
    }

    public function attachments(): array
    {
        if (! $this->order->invoice_pdf) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $this->order->invoice_pdf)
                ->as('Fatura-'.$this->order->invoice_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
