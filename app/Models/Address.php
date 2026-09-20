<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIsCorporateAttribute(): bool
    {
        return $this->invoice_type === 'corporate';
    }

    /** Siparişe kopyalanacak hâli — sonradan adres değişse de sipariş bozulmaz. */
    public function toSnapshot(): array
    {
        return $this->only([
            'name', 'phone', 'line1', 'line2', 'district', 'city', 'postal_code',
            'invoice_type', 'company_name', 'tax_office', 'tax_number',
        ]);
    }
}
