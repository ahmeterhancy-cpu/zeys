<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
