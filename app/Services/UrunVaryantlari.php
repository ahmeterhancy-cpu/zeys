<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Ozellik;
use App\Models\OzellikDegeri;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ReturnRequestItem;
use Illuminate\Support\Facades\DB;

/**
 * Ürün formundaki "Varyantlar" sekmesi ↔ varyant matrisi.
 *
 * WooCommerce akışı: ortak kütüphaneden özellik ve değer seçilir, ürün
 * kaydedilince bütün kombinasyonlar kendiliğinden oluşur. Ayrı bir
 * "kombinasyonları üret" adımı yok.
 *
 * Seçimden çıkarılan kombinasyonlar:
 *  - hiç satılmamış ve rezervi yoksa SİLİNİR,
 *  - sipariş/iade geçmişi varsa SATIŞTAN KALKAR (is_active = false) —
 *    geçmiş siparişlerin satırları ve iade talepleri ona bağlı.
 * Kullanılmayan değer ve eksen satırları temizlenir; vitrinde yalnız
 * satıştaki varyantların değerleri görünür.
 */
class UrunVaryantlari
{
    public function __construct(private VariantMatrix $matris) {}

    /**
     * Formun başlangıç durumu: ürünün eksenleri kütüphane kimlikleriyle.
     *
     * @return list<array{ozellik_id: int, degerler: list<string>}>
     */
    public function formDurumu(Product $product): array
    {
        return $product->options()
            ->with('values')
            ->orderBy('position')
            ->get()
            ->filter(fn (ProductOption $o) => $o->ozellik_id)
            ->map(fn (ProductOption $o) => [
                'ozellik_id' => $o->ozellik_id,
                'degerler' => $o->values
                    // Satıştan kalkmış (geçmişi olduğu için tutulan) değerler seçili görünmez
                    ->filter(fn (ProductOptionValue $v) => $v->ozellik_degeri_id
                        && $v->variants()->where('is_active', true)->exists())
                    ->pluck('ozellik_degeri_id')
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Formdaki çip seçimi (ozellik_id => [değer id]) → eksen listesi,
     * kütüphane sırasıyla (vitrinde seçiciler bu sırayla görünür).
     *
     * @return list<array{ozellik_id: int, degerler: list<string>}>
     */
    public static function seciminEksenleri(array $secim): array
    {
        $secim = array_filter($secim, fn ($degerler) => ! empty($degerler));

        return Ozellik::whereIn('id', array_keys($secim))->orderBy('sira')->orderBy('ad')->pluck('id')
            ->map(fn ($id) => ['ozellik_id' => $id, 'degerler' => array_values(array_map('strval', (array) $secim[$id]))])
            ->all();
    }

    /** Kombinasyonun sırasız kimliği: kütüphane değer id'leri, sıralı. */
    public static function kombinasyonAnahtari(array $degerIdleri): string
    {
        $ids = array_map('intval', $degerIdleri);
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * Seçime göre oluşacak kombinasyonlar — kaydetmeden önce formda
     * tablo olarak gösterilir.
     *
     * @return list<array{anahtar: string, etiket: string}>
     */
    public function onizleme(array $secim): array
    {
        $eksenler = static::seciminEksenleri($secim);

        if ($eksenler === []) {
            return [];
        }

        $tumu = OzellikDegeri::whereIn('id', collect($eksenler)->pluck('degerler')->flatten()->all())
            ->orderBy('sira')->orderBy('id')->get();

        $listeler = collect($eksenler)
            ->map(fn ($e) => $tumu->where('ozellik_id', $e['ozellik_id'])->values()->all())
            ->filter()
            ->values()
            ->all();

        return array_map(fn (array $kombinasyon) => [
            'anahtar' => static::kombinasyonAnahtari(array_map(fn (OzellikDegeri $d) => $d->id, $kombinasyon)),
            'etiket' => implode(' / ', array_map(fn (OzellikDegeri $d) => $d->deger, $kombinasyon)),
        ], $this->matris->cartesian($listeler));
    }

    /**
     * @param  list<array{ozellik_id: int|string|null, degerler: list<int|string>}>  $eksenler
     * @param  array{fiyat?: float|string|null, eski_fiyat?: float|string|null, stok?: int|string|null}  $varsayilan
     * @return array{toplam: int, eklenen: int, silinen: int, kaldirilan: int}
     */
    public function esitle(Product $product, array $eksenler, array $varsayilan = []): array
    {
        $eksenler = collect($eksenler)
            ->filter(fn ($e) => filled($e['ozellik_id'] ?? null) && ! empty($e['degerler']))
            ->unique('ozellik_id')
            ->values();

        return DB::transaction(function () use ($product, $eksenler, $varsayilan) {
            $once = $product->variants()->count();

            // Kaydetmeden önce satışta olan değerler (geri eklenen değeri tanımak için)
            $oncekiAktifDegerler = ProductOptionValue::whereHas('option', fn ($q) => $q->where('product_id', $product->id))
                ->whereHas('variants', fn ($v) => $v->where('is_active', true))
                ->pluck('id')
                ->all();

            $matris = [];
            foreach ($eksenler as $e) {
                $ozellik = Ozellik::findOrFail($e['ozellik_id']);
                $degerler = OzellikDegeri::where('ozellik_id', $ozellik->id)
                    ->whereIn('id', $e['degerler'])
                    ->orderBy('sira')
                    ->orderBy('id')
                    ->get();

                $matris[$ozellik->ad] = [
                    'kind' => $ozellik->tur,
                    'ozellik_id' => $ozellik->id,
                    'values' => $degerler->map(fn (OzellikDegeri $d) => [
                        'value' => $d->deger,
                        'color_hex' => $d->renk_kodu,
                        'ozellik_degeri_id' => $d->id,
                    ])->all(),
                ];
            }

            $fiyat = (float) ($varsayilan['fiyat'] ?? 0) ?: (float) $product->min_price;

            if ($matris !== []) {
                $this->matris->generate($product, $matris, $fiyat, [
                    'compare_at_price' => filled($varsayilan['eski_fiyat'] ?? null) ? (float) $varsayilan['eski_fiyat'] : null,
                    'stock' => (int) ($varsayilan['stok'] ?? 0),
                ]);
            }

            [$silinen, $kaldirilan] = $this->disardakileriTemizle($product->fresh(), $matris, $oncekiAktifDegerler);

            $this->matris->refreshProduct($product->fresh());

            $toplam = $product->variants()->count();

            return [
                'toplam' => $toplam,
                'eklenen' => max(0, $toplam - $once + $silinen),
                'silinen' => $silinen,
                'kaldirilan' => $kaldirilan,
            ];
        });
    }

    /** @return array{0: int, 1: int} [silinen, satıştan kaldırılan] */
    private function disardakileriTemizle(Product $product, array $matris, array $oncekiAktifDegerler = []): array
    {
        $secilenEksenler = array_keys($matris);

        // Geçerli kombinasyonlar: seçili eksenlerin seçili değerlerinin kartezyeni
        $degerIdleri = [];
        foreach ($product->options()->with('values')->orderBy('position')->get() as $eksen) {
            if (! in_array($eksen->name, $secilenEksenler, true)) {
                continue;
            }
            $secili = array_column($matris[$eksen->name]['values'], 'value');
            $degerIdleri[] = $eksen->values->whereIn('value', $secili)->pluck('id')->all();
        }

        $gecerli = [];
        if ($degerIdleri !== []) {
            foreach ($this->matris->cartesian($degerIdleri) as $k) {
                $gecerli[$this->matris->fingerprint($k)] = true;
            }
        }

        $silinen = 0;
        $kaldirilan = 0;

        foreach ($product->variants()->with('optionValues')->get() as $varyant) {
            $degerleri = $varyant->optionValues->pluck('id')->all();

            if (isset($gecerli[$this->matris->fingerprint($degerleri)])) {
                /*
                 * Daha önce seçimden çıkarılıp (geçmişi olduğu için) satıştan
                 * kaldırılmış değer geri eklendiyse varyant satışa döner.
                 * Yönetici tek bir varyantı elle kapattıysa (değerleri hâlâ
                 * başka satıştaki varyantlarda) dokunulmaz.
                 */
                if (! $varyant->is_active && array_diff($degerleri, $oncekiAktifDegerler) !== []) {
                    $varyant->update(['is_active' => true]);
                }

                continue;
            }

            $gecmisVar = $varyant->reserved > 0
                || OrderItem::where('product_variant_id', $varyant->id)->exists()
                || ReturnRequestItem::where('exchange_variant_id', $varyant->id)->exists();

            if ($gecmisVar) {
                if ($varyant->is_active) {
                    $varyant->update(['is_active' => false]);
                    $kaldirilan++;
                }
            } else {
                $varyant->delete();
                $silinen++;
            }
        }

        // Hiçbir varyantın kullanmadığı değerler ve boş eksenler
        foreach ($product->options()->with('values')->get() as $eksen) {
            foreach ($eksen->values as $deger) {
                // Fotoğrafı olan renk değeri silinmez: renk galerisi ona bağlı (cascade)
                if (! $deger->variants()->exists() && ! $deger->media()->exists()) {
                    $deger->delete();
                }
            }

            if (! $eksen->values()->exists()) {
                $eksen->delete();
            }
        }

        return [$silinen, $kaldirilan];
    }
}
