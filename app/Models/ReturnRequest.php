<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'payment_refunded_at' => 'datetime',
        'shipped_back_at' => 'datetime',
        'received_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public static function nextNumber(): string
    {
        $prefix = 'IAD-'.now()->format('ymd');

        $last = static::where('number', 'like', $prefix.'-%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getIsExchangeAttribute(): bool
    {
        return $this->type === 'exchange';
    }

    /** Müşteri hâlâ vazgeçebilir mi? */
    public function getIsCancellableAttribute(): bool
    {
        return in_array($this->status, ['opened', 'awaiting_shipment'], true);
    }

    public function getIsOpenAttribute(): bool
    {
        return ! in_array($this->status, ['completed', 'rejected', 'cancelled'], true);
    }

    /** Panelde ve müşteri sayfasında gösterilecek Türkçe durum. */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'opened' => 'Talep alındı',
            'awaiting_shipment' => 'Kargo bekleniyor',
            'received' => 'Teslim alındı',
            'approved' => 'Onaylandı',
            'rejected' => 'Reddedildi',
            'completed' => $this->is_exchange ? 'Değişim tamamlandı' : 'İade tamamlandı',
            'cancelled' => 'İptal edildi',
            default => $this->status,
        };
    }
}
