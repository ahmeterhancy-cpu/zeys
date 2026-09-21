<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ortak özellik (Beden, Renk…) — WooCommerce'teki "global attribute".
 * Ürünler değerlerini buradan seçer. Bkz. App\Services\UrunVaryantlari.
 */
class Ozellik extends Model
{
    protected $table = 'ozellikler';

    protected $guarded = [];

    public const TURLER = [
        'text' => 'Yazı (S, M, L…)',
        'color' => 'Renk noktası',
    ];

    protected static function booted(): void
    {
        // Ad ya da tür değişirse ürünlerdeki kopyası da değişir
        static::updated(function (Ozellik $o) {
            if ($o->wasChanged(['ad', 'tur'])) {
                ProductOption::where('ozellik_id', $o->id)->update(['name' => $o->ad, 'kind' => $o->tur]);
            }
        });
    }

    public function degerler(): HasMany
    {
        return $this->hasMany(OzellikDegeri::class)->orderBy('sira')->orderBy('id');
    }

    public function urunEksenleri(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }
}
