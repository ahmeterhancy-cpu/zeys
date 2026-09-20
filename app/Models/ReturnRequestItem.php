<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    protected $guarded = [];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** Değişimde gönderilecek yeni varyant. */
    public function exchangeVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'exchange_variant_id');
    }

    /** İade edilecek tutar — sipariş anındaki birim fiyattan. */
    public function refundValue(): float
    {
        return round((float) $this->orderItem->unit_price * $this->quantity, 2);
    }
}
