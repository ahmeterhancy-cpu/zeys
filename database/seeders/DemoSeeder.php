<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Ozellik;
use App\Models\OzellikDegeri;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SizeChart;
use App\Services\UrunVaryantlari;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DEMO veri.
 *
 * ⚠️ Adı bilerek "Demo": bu seeder panelden yapılan düzenlemeleri EZER.
 * Canlı veritabanında asla çalıştırılmamalıdır. Ürün adları ve fiyatlar
 * uydurmadır, gerçek koleksiyonu temsil etmez.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! config('shop.demo_seed_izin')) {
            $this->command?->error('DemoSeeder canlı ortamda çalıştırılamaz. Vitrini demo içerikle doldurmak istiyorsanız .env içine DEMO_SEED_IZIN=true yazın ve iş bitince kaldırın.');

            return;
        }

        $bedenTablosu = SizeChart::firstOrCreate(
            ['slug' => 'ust-giyim'],
            [
                'name' => 'Üst Giyim',
                'note' => 'Ölçüler cm cinsindendir ve giysi ölçüsüdür.',
                'columns' => ['Beden', 'Göğüs', 'Bel', 'Boy'],
                'rows' => [
                    ['S', '88', '72', '60'],
                    ['M', '92', '76', '62'],
                    ['L', '96', '80', '64'],
                    ['XL', '100', '84', '66'],
                ],
            ]
        );

        $kategoriler = [];

        foreach ([['Elbise', 'elbise'], ['Üst Giyim', 'ust-giyim'], ['Alt Giyim', 'alt-giyim']] as [$ad, $slug]) {
            $kategoriler[$slug] = Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $ad, 'size_chart_id' => $bedenTablosu->id, 'is_active' => true]
            );
        }

        $koleksiyonlar = [];

        foreach ([
            ['Yaz 26', 'yaz-26', 'Hafif kumaşlar, açık tonlar.'],
            ['Basic', 'basic', 'Her gardıropta duran temel parçalar.'],
        ] as $i => [$ad, $slug, $aciklama]) {
            $koleksiyonlar[$slug] = Collection::firstOrCreate(
                ['slug' => $slug],
                ['name' => $ad, 'description' => $aciklama, 'position' => $i, 'is_active' => true]
            );
        }

        $renkler = [
            'siyah' => ['value' => 'Siyah', 'color_hex' => '#1c1a15'],
            'bej' => ['value' => 'Bej', 'color_hex' => '#d8c9ae'],
            'lacivert' => ['value' => 'Lacivert', 'color_hex' => '#1e2a44'],
            'ekru' => ['value' => 'Ekru', 'color_hex' => '#f0e9dc'],
        ];

        $urunler = [
            ['Saten Midi Elbise', 'elbise', 'yaz-26', 2890, ['S', 'M', 'L'], ['siyah', 'bej'], 'Yeni'],
            ['Keten Gömlek', 'ust-giyim', 'yaz-26', 1490, ['S', 'M', 'L', 'XL'], ['ekru', 'bej'], null],
            ['Oversize Triko', 'ust-giyim', 'basic', 1790, ['M', 'L'], ['siyah', 'ekru'], null],
            ['Yüksek Bel Pantolon', 'alt-giyim', 'basic', 1990, ['S', 'M', 'L'], ['siyah', 'lacivert'], null],
            ['Krep Bluz', 'ust-giyim', 'yaz-26', 1290, ['S', 'M', 'L'], ['ekru', 'siyah'], 'Yeni'],
            ['Pileli Midi Etek', 'alt-giyim', 'yaz-26', 1690, ['S', 'M'], ['bej', 'lacivert'], null],
            ['Kruvaze Ceket', 'ust-giyim', 'basic', 3490, ['M', 'L'], ['lacivert', 'siyah'], null],
            ['Askılı Yazlık Elbise', 'elbise', 'yaz-26', 2190, ['S', 'M', 'L'], ['ekru', 'bej'], null],
        ];

        $sayac = 1;

        foreach ($urunler as [$ad, $kategori, $koleksiyon, $fiyat, $bedenler, $renkAnahtarlari, $rozet]) {
            $product = Product::firstOrCreate(
                ['slug' => Str::slug($ad)],
                [
                    'name' => $ad,
                    'collection_id' => $koleksiyonlar[$koleksiyon]->id,
                    'base_sku' => 'ZEYS-'.str_pad((string) $sayac, 3, '0', STR_PAD_LEFT),
                    'short_description' => 'Demo ürün — açıklama ve fotoğraf mağaza tarafından girilecek.',
                    'material' => '%100 pamuk',
                    'care_notes' => '30 derecede yıkayınız, ütü orta ısı.',
                    'model_note' => 'Model 1.75 m boyunda ve M beden giymektedir.',
                    'badge' => $rozet,
                    'is_active' => true,
                    'is_featured' => $rozet === 'Yeni',
                    'position' => $sayac,
                ]
            );

            $product->categories()->syncWithoutDetaching([$kategoriler[$kategori]->id]);

            /*
             * Panelle aynı yol: ortak özellik kütüphanesinden (Beden, Renk)
             * seçip eşitle — ürün eksenleri kütüphaneye bağlı olsun ki
             * panelin Varyantlar sekmesinde seçili görünsün.
             */
            $beden = Ozellik::firstOrCreate(['ad' => 'Beden'], ['tur' => 'text']);
            $renk = Ozellik::firstOrCreate(['ad' => 'Renk'], ['tur' => 'color']);

            app(UrunVaryantlari::class)->esitle($product, [
                ['ozellik_id' => $beden->id, 'degerler' => collect($bedenler)
                    ->map(fn ($b) => OzellikDegeri::firstOrCreate(['ozellik_id' => $beden->id, 'deger' => $b])->id)->all()],
                ['ozellik_id' => $renk->id, 'degerler' => collect($renkAnahtarlari)
                    ->map(fn ($k) => OzellikDegeri::firstOrCreate(
                        ['ozellik_id' => $renk->id, 'deger' => $renkler[$k]['value']],
                        ['renk_kodu' => $renkler[$k]['color_hex']],
                    )->id)->all()],
            ], ['fiyat' => $fiyat]);

            // Gerçekçi stok dağılımı: bazı bedenler tükenmiş olsun
            foreach ($product->fresh()->variants as $i => $variant) {
                $variant->update(['stock' => $i % 4 === 0 ? 0 : random_int(2, 9)]);
            }

            $sayac++;
        }

        $gorselSayisi = $this->gorselleriYukle($renkler, $kategoriler, $koleksiyonlar);

        $this->command?->info('Demo: '.count($urunler).' ürün, '
            .ProductVariant::count().' varyant, '.$gorselSayisi.' görsel.');
    }

    /**
     * Demo fotoğrafları (database/seeders/demo-gorseller, kaynak: KAYNAK.md).
     *
     * Her ürünün iki rengi var, her renge bir fotoğraf: ilk renk kapak olur,
     * ikinci renginki "genel" galeriye de eklenir ki kartın üzerine gelince
     * ikinci görsel görünsün. Renk seçilince ürün sayfası o rengin
     * fotoğrafına geçer.
     *
     * Tekrar çalıştırılabilir: yalnız urunler/demo/ altındaki kayıtlar
     * silinip yeniden yazılır, mağazanın kendi yüklediği görsellere dokunulmaz.
     */
    private function gorselleriYukle(array $renkler, array $kategoriler, array $koleksiyonlar): int
    {
        $kaynak = database_path('seeders/demo-gorseller');

        if (! is_dir($kaynak)) {
            return 0;
        }

        $disk = Storage::disk('public');
        $yol = fn (string $ad) => 'urunler/demo/'.$ad.'.jpg';
        $kopyala = function (string $ad) use ($kaynak, $disk, $yol): ?string {
            $dosya = $kaynak.'/'.$ad.'.jpg';
            if (! is_file($dosya)) {
                return null;
            }
            $disk->put($yol($ad), file_get_contents($dosya));

            return $yol($ad);
        };

        $sayi = 0;

        foreach (Product::with('options.values')->get() as $product) {
            $renkEkseni = $product->options->firstWhere('kind', 'color');
            if (! $renkEkseni) {
                continue;
            }

            ProductMedia::where('product_id', $product->id)->where('path', 'like', 'urunler/demo/%')->delete();

            $renkYollari = [];
            foreach ($renkEkseni->values as $deger) {
                // "Siyah" → "siyah" (dosya adındaki renk anahtarı)
                $renkAnahtari = array_search($deger->value, array_map(fn ($r) => $r['value'], $renkler), true);
                $p = $renkAnahtari !== false ? $kopyala($product->slug.'-'.$renkAnahtari) : null;

                if ($p) {
                    ProductMedia::create([
                        'product_id' => $product->id,
                        'product_option_value_id' => $deger->id,
                        'path' => $p,
                        'alt' => $product->name.' — '.$deger->value,
                        'position' => count($renkYollari),
                    ]);
                    $renkYollari[] = $p;
                    $sayi++;
                }
            }

            if ($renkYollari === []) {
                continue;
            }

            // Kart üzerine gelince ve ürün sayfası küçük görselleri için genel galeri
            foreach (array_slice($renkYollari, 1) as $i => $p) {
                ProductMedia::create([
                    'product_id' => $product->id,
                    'path' => $p,
                    'alt' => $product->name,
                    'position' => 10 + $i,
                ]);
            }

            $product->forceFill([
                'hero_image' => $renkYollari[0],
                'short_description' => 'Demo ürün — fotoğraf temsilîdir, mağazanın gerçek ürün fotoğraflarıyla değiştirilecek.',
            ])->saveQuietly();
        }

        foreach ($kategoriler as $slug => $kategori) {
            if ($p = $kopyala('kategori-'.$slug)) {
                $kategori->forceFill(['image' => $p])->save();
                $sayi++;
            }
        }

        foreach ($koleksiyonlar as $slug => $koleksiyon) {
            if ($p = $kopyala('koleksiyon-'.$slug)) {
                $koleksiyon->forceFill(['image' => $p])->save();
                $sayi++;
            }
        }

        return $sayi;
    }
}
