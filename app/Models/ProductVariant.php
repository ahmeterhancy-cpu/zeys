<?php

namespace App\Models;

use App\Observers\ProductVariantObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy(ProductVariantObserver::class)]
class ProductVariant extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_value');
    }

    /**
     * Gerçekten satılabilir adet: rezerve edilenler düşülmüş hâli.
     * Sepet ve sipariş bu sayıya bakar, ham `stock`a değil.
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock - $this->reserved);
    }

    public function getIsOrderableAttribute(): bool
    {
        return $this->is_active && $this->available_stock > 0;
    }

    /** "Siyah / M" — seçenek değerlerinden eksen sırasına göre kurulur. */
    public function getLabelAttribute(): string
    {
        return $this->optionValues
            ->sortBy(fn (ProductOptionValue $v) => $v->option?->position ?? 0)
            ->pluck('value')
            ->implode(' / ');
    }
}
