<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ana sayfa slaytları ve afişleri — panelden yönetilir
 * (Vitrin → Slayt ve Afişler).
 *
 * Bir yerde hiç yayında kayıt yoksa ana sayfa eski otomatik davranışına
 * döner (görselli koleksiyonlar / markanın kendi slaytı). Yani bu tablo
 * boşken site bozulmaz.
 */
class Banner extends Model
{
    protected $guarded = [];

    protected $casts = [
        'aktif' => 'boolean',
        'baslangic' => 'datetime',
        'bitis' => 'datetime',
    ];

    public const YERLER = [
        'slayt' => 'Ana slayt (en üst)',
        'afis' => 'Üçlü afiş (kategori dairelerinin altı)',
        'genis' => 'İkili geniş afiş (ürünlerin altı)',
    ];

    /** Yayında olanlar: aktif ve (varsa) tarih aralığının içinde. */
    public function scopeYayinda(Builder $q, string $yer): Builder
    {
        return $q->where('yer', $yer)
            ->where('aktif', true)
            ->where(fn ($t) => $t->whereNull('baslangic')->orWhere('baslangic', '<=', now()))
            ->where(fn ($t) => $t->whereNull('bitis')->orWhere('bitis', '>=', now()))
            ->orderBy('sira')
            ->orderBy('id');
    }

    /** Bağlantı: "/yol" ya da tam adres; boşsa null. */
    public function getAdresAttribute(): ?string
    {
        if (blank($this->baglanti)) {
            return null;
        }

        return str_starts_with($this->baglanti, 'http') ? $this->baglanti : url($this->baglanti);
    }

    public function getDisBaglantiAttribute(): bool
    {
        return filled($this->baglanti)
            && str_starts_with($this->baglanti, 'http')
            && ! str_starts_with($this->baglanti, url('/'));
    }
}
