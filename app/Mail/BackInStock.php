<?php

namespace App\Mail;

use App\Models\ProductVariant;
use App\Support\EpostaMetni;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackInStock extends Mailable
{
    use Queueable, SerializesModels;

    /** Panelden değiştirilebilen metinler (bkz. App\Support\EpostaMetni) */
    public array $metin;

    public function __construct(public ProductVariant $variant)
    {
        $this->metin = EpostaMetni::al('stokta', [
            'urun' => $variant->product->name.($variant->label ? ' · '.$variant->label : ''),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->metin['konu']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.stokta',
            with: ['variant' => $this->variant],
        );
    }
}
