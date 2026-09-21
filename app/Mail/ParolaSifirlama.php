<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParolaSifirlama extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $baglanti, public int $dakika) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Parola sıfırlama — '.config('shop.ad'));
    }

    public function content(): Content
    {
        return new Content(view: 'eposta.parola-sifirlama');
    }
}
