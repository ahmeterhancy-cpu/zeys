<?php

namespace App\Mail;

use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Mağazaya: varyantın rafta kalan adedi eşiğe indi ya da bitti. */
class DusukStok extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProductVariant $variant) {}

    public function envelope(): Envelope
    {
        $durum = $this->variant->stock < 1 ? 'Tükendi' : 'Stok azaldı ('.$this->variant->stock.' adet)';

        return new Envelope(
            subject: $durum.' — '.$this->variant->product->name.($this->variant->label ? ' · '.$this->variant->label : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'eposta.dusuk-stok',
            with: ['variant' => $this->variant],
        );
    }
}
