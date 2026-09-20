<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LegalDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_current' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** Yürürlükteki sürüm. */
    public static function current(string $slug): ?self
    {
        return static::where('slug', $slug)->where('is_current', true)->first();
    }

    /**
     * Yeni sürüm yayımla. Eski sürüm SİLİNMEZ, yalnızca yürürlükten kalkar —
     * o sürüme dayanan siparişler okunabilir kalmalı.
     */
    public static function publish(string $slug, string $title, string $body, ?string $version = null): self
    {
        return DB::transaction(function () use ($slug, $title, $body, $version) {
            static::where('slug', $slug)->update(['is_current' => false]);

            return static::create([
                'slug' => $slug,
                'version' => $version ?: static::nextVersion($slug),
                'title' => $title,
                'body' => $body,
                'is_current' => true,
                'published_at' => now(),
            ]);
        });
    }

    /** 2026-09-20.1 — aynı gün ikinci yayında .2 olur. */
    public static function nextVersion(string $slug): string
    {
        $today = now()->format('Y-m-d');

        $count = static::where('slug', $slug)
            ->where('version', 'like', $today.'.%')
            ->count();

        return $today.'.'.($count + 1);
    }
}
