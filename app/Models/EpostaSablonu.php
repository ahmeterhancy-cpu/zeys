<?php

namespace App\Models;

use App\Support\EpostaMetni;
use Illuminate\Database\Eloquent\Model;

/** Panelden değiştirilen e-posta metni. Bkz. App\Support\EpostaMetni */
class EpostaSablonu extends Model
{
    protected $table = 'eposta_sablonlari';

    protected $guarded = [];

    /** Panel listesi her şablonu göstersin: eksik satırlar (boş = varsayılan) açılır. */
    public static function eksikleriAc(): void
    {
        foreach (array_keys(EpostaMetni::SABLONLAR) as $anahtar) {
            static::firstOrCreate(['anahtar' => $anahtar]);
        }
    }

    public function getAdAttribute(): string
    {
        return EpostaMetni::SABLONLAR[$this->anahtar]['ad'] ?? $this->anahtar;
    }

    public function getDegistirildiMiAttribute(): bool
    {
        return filled($this->konu) || filled($this->baslik) || filled($this->metin) || filled($this->not);
    }
}
