<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SizeChart extends Model
{
    protected $guarded = [];

    protected $casts = [
        'columns' => 'array',
        'rows' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (SizeChart $chart) {
            if (blank($chart->slug)) {
                $chart->slug = Str::slug($chart->name) ?: 'beden-tablosu';
            }
        });
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Her satırın hücre sayısı sütun başlığı sayısıyla aynı mı?
     * Değilse ürün sayfasındaki tablo kayar.
     */
    public function isConsistent(): bool
    {
        $sutun = count($this->columns ?? []);

        foreach ($this->rows ?? [] as $satir) {
            if (count((array) $satir) !== $sutun) {
                return false;
            }
        }

        return true;
    }
}
