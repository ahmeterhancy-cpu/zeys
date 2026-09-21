<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $guarded = [];

    protected $casts = [
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (blank($product->slug)) {
                $product->slug = static::uniqueSlug($product->name, $product->id);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'urun';
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('position');
    }

    /** Kombin önerisi — "birlikte kullan". */
    public function related(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product', 'product_id', 'related_product_id')
            ->withPivot('position')
            ->orderBy('product_product.position');
    }

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
    }

    /**
     * Ürüne özel beden tablosu varsa o, yoksa kategorininki.
     * Kategori ağacında ilk bulunan kazanır.
     */
    public function resolvedSizeChart(): ?SizeChart
    {
        if ($this->size_chart_id) {
            return $this->sizeChart;
        }

        foreach ($this->categories as $category) {
            if ($chart = $category->resolvedSizeChart()) {
                return $chart;
            }
        }

        return null;
    }

    /** Satılabilir stoğu olan varyant var mı. */
    public function getIsOrderableAttribute(): bool
    {
        return $this->variants->contains(fn (ProductVariant $v) => $v->is_orderable);
    }

    /**
     * Ürün kartındaki renk noktaları.
     *
     * Eksen adına değil `kind = color`a bakar: mağaza ileride ekseni
     * "Ton" diye adlandırsa bile renk noktaları çizilmeye devam eder.
     */
    public function getRenklerAttribute(): \Illuminate\Support\Collection
    {
        return $this->options
            ->where('kind', 'color')
            ->flatMap(fn (ProductOption $o) => $o->values)
            ->unique('id')
            ->values();
    }

    public function getHasPriceRangeAttribute(): bool
    {
        return $this->min_price != $this->max_price;
    }

    /**
     * Ürün kartının ihtiyacı olan her şey tek seferde: renk noktaları,
     * koleksiyon adı, üzerine gelince değişen ikinci görsel ve eski fiyat.
     * Kart listeleyen her sorgu bunu kullanır; yoksa 24'lük ızgara ürün
     * başına 3-4 sorgu atar.
     */
    public function scopeKartIcin(Builder $q): Builder
    {
        return $q
            ->with([
                'options.values',
                'collection',
                'media' => fn ($m) => $m->whereNull('product_option_value_id'),
            ])
            ->withMax(['variants as eski_fiyat' => fn ($v) => $v->where('is_active', true)], 'compare_at_price');
    }

    /**
     * Kartta gösterilecek eski fiyat. Yalnız satış fiyatından BÜYÜKSE —
     * aksi hâlde "indirim" yazmak yanıltıcı olur. Bedenler arasında fiyat
     * farkı varsa en düşük fiyat baz alınır (kartta da o yazıyor).
     */
    public function getKartEskiFiyatAttribute(): ?float
    {
        $eski = (float) ($this->eski_fiyat ?? 0);

        return $eski > (float) $this->min_price ? $eski : null;
    }

    public function getIndirimOraniAttribute(): ?int
    {
        $eski = $this->kart_eski_fiyat;

        return $eski ? (int) round(($eski - (float) $this->min_price) / $eski * 100) : null;
    }

    /** Üzerine gelince gösterilecek ikinci görsel (kapaktan farklı ilk galeri görseli). */
    public function getIkinciGorselAttribute(): ?string
    {
        if (! $this->relationLoaded('media')) {
            return null;
        }

        return $this->media->pluck('path')->first(fn ($p) => $p !== $this->hero_image);
    }
}
