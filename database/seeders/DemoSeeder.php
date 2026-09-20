<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SizeChart;
use App\Services\VariantMatrix;
use Illuminate\Database\Seeder;
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
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder canlı ortamda çalıştırılamaz.');

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

        $matris = app(VariantMatrix::class);
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

            $matris->generate($product, [
                'Beden' => ['kind' => 'text', 'values' => $bedenler],
                'Renk' => ['kind' => 'color', 'values' => array_map(
                    fn ($k) => $renkler[$k],
                    $renkAnahtarlari
                )],
            ], $fiyat);

            // Gerçekçi stok dağılımı: bazı bedenler tükenmiş olsun
            foreach ($product->fresh()->variants as $i => $variant) {
                $variant->update(['stock' => $i % 4 === 0 ? 0 : random_int(2, 9)]);
            }

            $sayac++;
        }

        $this->command?->info('Demo: '.count($urunler).' ürün, '
            .ProductVariant::count().' varyant oluşturuldu.');
    }
}
