<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OzellikDegeri extends Model
{
    protected $table = 'ozellik_degerleri';

    protected $guarded = [];

    protected static function booted(): void
    {
        /*
         * Kütüphanede "Siyah" → "Kömür Siyahı" yapılırsa bütün ürünlerde
         * değişir; renk kodu da. Ürün tarafı kopya tuttuğu için elle
         * eşitlenmesi gerekiyor.
         */
        static::updated(function (OzellikDegeri $d) {
            if ($d->wasChanged(['deger', 'renk_kodu'])) {
                ProductOptionValue::where('ozellik_degeri_id', $d->id)
                    ->update(['value' => $d->deger, 'color_hex' => $d->renk_kodu]);
            }
        });
    }

    public function ozellik(): BelongsTo
    {
        return $this->belongsTo(Ozellik::class);
    }

    public function urunDegerleri(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class);
    }

    /** Panelde seçenek etiketi: renkse küçük nokta ile. */
    public function getEtiketHtmlAttribute(): string
    {
        $ad = e($this->deger);

        if (! $this->renk_kodu) {
            return $ad;
        }

        return '<span style="display:inline-flex;align-items:center;gap:6px">'
            .'<span style="width:12px;height:12px;border-radius:50%;border:1px solid #ccc;background:'.e($this->renk_kodu).'"></span>'
            .$ad.'</span>';
    }
}
