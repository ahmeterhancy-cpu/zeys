<?php

namespace App\Mail;

use App\Support\EpostaMetni;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParolaSifirlama extends Mailable
{
    use Queueable, SerializesModels;

    /** Panelden değiştirilebilen metinler (bkz. App\Support\EpostaMetni) */
    public array $metin;

    public function __construct(public string $baglanti, public int $dakika)
    {
        $this->metin = EpostaMetni::al('parola-sifirlama', ['dakika' => $dakika]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->metin['konu']);
    }

    public function content(): Content
    {
        return new Content(view: 'eposta.parola-sifirlama');
    }
}
