<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Observers\ProductVariantObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Beden × Renk matrisi.
 *
 * Eksenler (product_options) ve değerleri (product_option_values) tanımlanır,
 * buradan tüm kombinasyonlar üretilir ve her biri bir product_variants satırı
 * olur. Stok ve fiyat varyantta durur — ürün seviyesindeki min/max/total
 * alanları yalnızca listeleme önbelleğidir.
 */
class VariantMatrix
{
    /**
     * Eksenleri kur ve eksik kombinasyonları üret.
     *
     * $axes formatı:
     *   ['Beden' => ['kind' => 'text',  'values' => ['S', 'M', 'L']],
     *    'Renk'  => ['kind' => 'color', 'values' => [['value' => 'Siyah', 'color_hex' => '#111111']]]]
     *
     * Var olan varyantlar KORUNUR (fiyat/stok kaybolmaz); yalnızca eksik
     * kombinasyonlar eklenir. Eksenden çıkarılan değerlerin varyantları
     * silinmez, pasife alınır — geçmiş siparişlerin satırları onlara bağlı.
     */
    public function generate(Product $product, array $axes, float $defaultPrice = 0): void
    {
        // Matris üretilirken her satırda önbellek tazelenmesin; sonda bir kez.
        ProductVariantObserver::$muted = true;

        try {
            $this->buildMatrix($product, $axes, $defaultPrice);
        } finally {
            ProductVariantObserver::$muted = false;
        }

        $this->refreshProduct($product->fresh());
    }

    private function buildMatrix(Product $product, array $axes, float $defaultPrice): void
    {
        DB::transaction(function () use ($product, $axes, $defaultPrice) {
            $valuesByAxis = [];
            $position = 0;

            foreach ($axes as $axisName => $config) {
                $option = ProductOption::updateOrCreate(
                    ['product_id' => $product->id, 'name' => $axisName],
                    ['kind' => $config['kind'] ?? 'text', 'position' => $position++],
                );

                $valuePosition = 0;
                $ids = [];

                foreach ($config['values'] as $raw) {
                    $row = is_array($raw) ? $raw : ['value' => $raw];

                    $value = ProductOptionValue::updateOrCreate(
                        ['product_option_id' => $option->id, 'value' => $row['value']],
                        [
                            'color_hex' => $row['color_hex'] ?? null,
                            'position' => $valuePosition++,
                        ],
                    );

                    $ids[] = $value->id;
                }

                $valuesByAxis[] = $ids;
            }

            if ($valuesByAxis === []) {
                return;
            }

            // Var olan kombinasyonların parmak izi
            $existing = [];
            foreach ($product->variants()->with('optionValues')->get() as $variant) {
                $existing[$this->fingerprint($variant->optionValues->pluck('id')->all())] = $variant;
            }

            $position = 0;

            foreach ($this->cartesian($valuesByAxis) as $combination) {
                $key = $this->fingerprint($combination);

                if (isset($existing[$key])) {
                    $existing[$key]->update(['position' => $position++]);

                    continue;
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->buildSku($product, $combination),
                    'price' => $defaultPrice,
                    'stock' => 0,
                    'reserved' => 0,
                    'is_active' => true,
                    'position' => $position++,
                ]);

                $variant->optionValues()->sync($combination);
            }
        });
    }

    /**
     * Listeleme önbelleğini yeniden hesapla.
     * Varyant her kaydedildiğinde/silindiğinde çağrılmalı.
     */
    public function refreshProduct(Product $product): void
    {
        $variants = $product->variants()->where('is_active', true)->get();

        $prices = $variants->pluck('price')->map(fn ($p) => (float) $p);

        $product->forceFill([
            'min_price' => $prices->min() ?? 0,
            'max_price' => $prices->max() ?? 0,
            // Önbellekte de satılabilir adet tutulur; ham stok değil.
            'total_stock' => $variants->sum(fn (ProductVariant $v) => $v->available_stock),
        ])->saveQuietly();
    }

    /**
     * Seçim yapıldıkça hangi eksen değerleri hâlâ seçilebilir?
     *
     * "Siyah M" bitmişken "Siyah L" duruyorsa, Siyah seçiliyken M sönük,
     * L seçilebilir görünmeli. Boş seçimle çağrılırsa stoğu olan tüm
     * değerleri döner.
     *
     * @param  array<int>  $selectedValueIds
     * @return array<int>
     */
    public function availableValueIds(Product $product, array $selectedValueIds = []): array
    {
        $variants = $product->variants()
            ->where('is_active', true)
            ->with('optionValues:id,product_option_id')
            ->get()
            ->filter(fn (ProductVariant $v) => $v->available_stock > 0);

        // Seçili değerlerin eksenleri tek sorguda çözülür — varyant başına
        // sorgu atılırsa ürün sayfası N+1'e düşer.
        $axisOfSelected = ProductOptionValue::whereIn('id', $selectedValueIds)
            ->pluck('product_option_id', 'id')
            ->all();

        // Bir değer, onu içeren VE seçili değerlerin tamamını içeren
        // stoklu bir varyant varsa seçilebilirdir.
        $available = [];

        foreach ($variants as $variant) {
            $ids = $variant->optionValues->pluck('id')->all();
            $axes = $variant->optionValues->pluck('product_option_id')->all();

            foreach ($selectedValueIds as $selected) {
                $axis = $axisOfSelected[$selected] ?? null;

                // Seçim, bu varyantın kendi ekseninde farklı bir değere
                // denk geliyorsa varyant elenir. Varyant o ekseni hiç
                // taşımıyorsa seçim onu bağlamaz.
                if ($axis !== null && in_array($axis, $axes, true) && ! in_array($selected, $ids, true)) {
                    continue 2;
                }
            }

            foreach ($ids as $id) {
                $available[$id] = true;
            }
        }

        return array_keys($available);
    }

    /**
     * Verilen kombinasyona TAM denk gelen varyant.
     *
     * "Tam" önemli: aranan değerlerin hepsini taşıyan ama fazladan bir ekseni
     * de olan varyant eşleşmemeli, yoksa sepete yanlış SKU girer. Bu yüzden
     * eşleşme adedi hem aranan hem varyant tarafında kontrol edilir.
     */
    public function findVariant(Product $product, array $valueIds): ?ProductVariant
    {
        $valueIds = array_values(array_unique($valueIds));

        if ($valueIds === []) {
            return null;
        }

        $id = DB::table('product_variant_option_value as pv')
            ->join('product_variants as v', 'v.id', '=', 'pv.product_variant_id')
            ->where('v.product_id', $product->id)
            ->whereIn('pv.product_option_value_id', $valueIds)
            ->groupBy('pv.product_variant_id')
            ->havingRaw('COUNT(pv.product_option_value_id) = ?', [count($valueIds)])
            ->pluck('pv.product_variant_id');

        return ProductVariant::whereIn('id', $id)
            ->withCount('optionValues')
            ->get()
            ->firstWhere('option_values_count', count($valueIds));
    }

    /** Sıradan bağımsız kombinasyon kimliği. */
    private function fingerprint(array $valueIds): string
    {
        sort($valueIds);

        return implode('-', $valueIds);
    }

    /**
     * Kartezyen çarpım: [[1,2],[3,4]] → [[1,3],[1,4],[2,3],[2,4]]
     *
     * @param  array<array<int>>  $axes
     * @return array<array<int>>
     */
    private function cartesian(array $axes): array
    {
        $result = [[]];

        foreach ($axes as $values) {
            $next = [];

            foreach ($result as $prefix) {
                foreach ($values as $value) {
                    $next[] = [...$prefix, $value];
                }
            }

            $result = $next;
        }

        return $result;
    }

    /** "ZEYS-001-SIYAH-M" — çakışırsa sonuna sayaç eklenir. */
    private function buildSku(Product $product, array $valueIds): string
    {
        $root = $product->base_sku ?: Str::slug($product->name);

        $parts = ProductOptionValue::whereIn('id', $valueIds)
            ->orderBy('product_option_id')
            ->pluck('value')
            ->map(fn ($v) => Str::upper(Str::slug($v)))
            ->all();

        $base = Str::upper(Str::slug($root)).'-'.implode('-', $parts);
        $sku = $base;
        $i = 2;

        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }
}
