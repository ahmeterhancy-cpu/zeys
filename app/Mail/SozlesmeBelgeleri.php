<?php

namespace App\Mail;

use App\Models\LegalDocument;
use App\Models\Order;
use App\Support\EpostaMetni;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Ön Bilgilendirme Formu + Mesafeli Satış Sözleşmesi — müşterinin
 * ONAYLADIĞI sürümler, siparişe özgü bilgilerle birlikte.
 *
 * Mesafeli Sözleşmeler Yönetmeliği sözleşmenin tüketiciye kalıcı bir
 * veri saklayıcısıyla (e-posta bunu karşılar) iletilmesini istiyor.
 * Önceden yalnızca sitede bağlantı vardı.
 *
 * Yürürlükteki değil, siparişte kayıtlı sürüm gönderilir: metin
 * sonradan değişmiş olabilir.
 */
class SozlesmeBelgeleri extends Mailable
{
    use Queueable, SerializesModels;

    public ?LegalDocument $onBilgi;

    public ?LegalDocument $sozlesme;

    public function __construct(public Order $order)
    {
        $this->onBilgi = LegalDocument::surum('on-bilgilendirme', $order->preinfo_version);
        $this->sozlesme = LegalDocument::surum('mesafeli-satis', $order->contract_version);
        $this->metin = EpostaMetni::al('sozlesme-belgeleri', [
            'ad' => $order->customer_name,
            'siparis_no' => $order->number,
        ]);
    }

    /** Panelden değiştirilebilen metinler (bkz. App\Support\EpostaMetni) */
    public array $metin;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->metin['konu']);
    }

    public function content(): Content
    {
        return new Content(view: 'eposta.sozlesme-belgeleri');
    }
}
