<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];

    protected $casts = [
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'payment_meta' => 'array',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'refunded_total' => 'decimal:2',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'contract_accepted_at' => 'datetime',
        'contract_sent_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class)->latest();
    }

    /** Vitrinde ve e-postada gösterilecek Türkçe durum. */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Ödeme bekleniyor',
            'paid' => 'Hazırlanıyor',
            'preparing' => 'Hazırlanıyor',
            'shipped' => 'Kargoda',
            'delivered' => 'Teslim edildi',
            'cancelled' => 'İptal edildi',
            'refunded' => 'İade edildi',
            default => $this->status,
        };
    }

    /** ZEY-260920-0001 — gün içinde artan sıra. */
    public static function nextNumber(): string
    {
        $prefix = 'ZEY-'.now()->format('ymd');

        $last = static::where('number', 'like', $prefix.'-%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === 'paid';
    }

    /** Faturada kullanılacak adres — ayrı verilmemişse teslimat adresi. */
    public function getInvoiceAddressAttribute(): array
    {
        return $this->billing_address ?: $this->shipping_address;
    }
}
