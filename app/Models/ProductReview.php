<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rating' => 'integer',
        'approved_at' => 'datetime',
    ];

    public const DURUMLAR = [
        'pending' => 'Onay bekliyor',
        'approved' => 'Yayında',
        'rejected' => 'Reddedildi',
    ];

    /**
     * Ürünün `rating` / `review_count` alanları yalnız ONAYLI yorumlardan
     * hesaplanan önbellektir. Yorum eklenince, onaylanınca, reddedilince
     * ya da silinince yeniden hesaplanır — elle yazılmaz.
     */
    protected static function booted(): void
    {
        static::saved(fn (ProductReview $r) => static::ozetTazele($r->product_id));
        static::deleted(fn (ProductReview $r) => static::ozetTazele($r->product_id));
    }

    public static function ozetTazele(int $productId): void
    {
        $ozet = static::query()
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('COUNT(*) as adet, AVG(rating) as ortalama')
            ->first();

        Product::whereKey($productId)->update([
            'review_count' => (int) $ozet->adet,
            'rating' => $ozet->adet > 0 ? round((float) $ozet->ortalama, 2) : null,
        ]);
    }

    /** "Ayşe Yılmaz" → "Ayşe Y." — tam ad vitrinde yayımlanmaz (KVKK). */
    public static function gorunenAd(string $adSoyad): string
    {
        $parcalar = preg_split('/\s+/u', trim($adSoyad), -1, PREG_SPLIT_NO_EMPTY);

        if ($parcalar === []) {
            return 'Müşteri';
        }

        $ad = array_shift($parcalar);
        $ad = self::buyukHarf(mb_substr($ad, 0, 1)).mb_substr($ad, 1);

        if ($parcalar === []) {
            return $ad;
        }

        return $ad.' '.self::buyukHarf(mb_substr(end($parcalar), 0, 1)).'.';
    }

    /** Türkçe büyük harf: mb_strtoupper('i') "I" verir, doğrusu "İ". */
    private static function buyukHarf(string $harf): string
    {
        return match ($harf) {
            'i' => 'İ',
            'ı' => 'I',
            default => mb_strtoupper($harf),
        };
    }

    public function scopeYayinda(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::DURUMLAR[$this->status] ?? $this->status;
    }
}
