<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Beden tablosu — kategori bazında. Ürün sayfasında açılır pencerede gösterilir.
        Schema::create('size_charts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('note')->nullable();   // "Ölçüler cm cinsindendir"
            $table->json('columns');            // ["Beden","Göğüs","Bel","Boy"]
            $table->json('rows');               // [["S","88","72","64"], ...]
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('size_chart_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        // Koleksiyon / sezon: "Yaz 26", "Basic"
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('size_chart_id')->nullable()->constrained()->nullOnDelete(); // kategoriyi ezer
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_sku')->nullable();       // varyant SKU'larının kökü
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->text('material')->nullable();         // "%100 pamuk"
            $table->text('care_notes')->nullable();       // yıkama talimatı
            $table->string('model_note')->nullable();     // "Model 1.75 m, M beden giyiyor"

            /*
             * Fiyat ve stok TEK YETKİLİ olarak product_variants'ta durur.
             * Aşağıdaki üç alan yalnızca LİSTELEME ÖNBELLEĞİDİR: varyant her
             * kaydedildiğinde App\Services\VariantMatrix::refreshProduct() yeniden
             * hesaplar. Hiçbir yerde bunlara yazarak fiyat belirlenmez.
             */
            $table->decimal('min_price', 10, 2)->default(0);
            $table->decimal('max_price', 10, 2)->default(0);
            $table->integer('total_stock')->default(0);

            $table->string('hero_image')->nullable();
            $table->string('badge')->nullable();          // "Yeni", "Son parçalar"
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('position')->default(0);

            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->default(0);

            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'position']);
            $table->index(['is_active', 'is_featured']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['category_id', 'product_id']);
        });

        // Varyant EKSENİ: "Beden", "Renk". Ürüne göre sayısı değişebilir.
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('text');  // text | color — vitrinde nasıl çizileceği
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        // Eksen DEĞERİ: S/M/L/XL — Siyah/Bej/Lacivert
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->string('color_hex')->nullable();  // yalnızca kind=color için
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_option_id', 'value']);
        });

        // SKU: her Beden × Renk kombinasyonu bir satır. STOK BURADA.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);

            /*
             * Ödemesi başlamış ama tamamlanmamış siparişlerin tuttuğu adet.
             * unsigned: başka bir projede negatife düşüp hayalet stok yarattı
             * ve GERÇEK fazla satış oldu. Uygulama tarafında da alt sınır var.
             */
            $table->unsignedInteger('reserved')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // Varyant <-> eksen değeri. JSON yerine pivot: "Siyah'ın stoğu var mı"
        // sorgusu JSON alanda pahalı, burada indeksli.
        Schema::create('product_variant_option_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained()->cascadeOnDelete();

            $table->unique(
                ['product_variant_id', 'product_option_value_id'],
                'variant_option_value_unique'
            );
            $table->index('product_option_value_id', 'variant_option_value_lookup');
        });

        // Renk başına galeri: option_value_id doluysa o renk seçilince gösterilir,
        // boşsa ürünün genel görseli.
        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'product_option_value_id'], 'product_media_lookup');
        });

        // Kombin / "birlikte kullan" önerisi
        Schema::create('product_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->unique(['product_id', 'related_product_id'], 'product_related_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_product');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_variant_option_value');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_option_values');
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('size_charts');
    }
};
