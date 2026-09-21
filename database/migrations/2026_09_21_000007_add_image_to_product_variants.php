<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            /*
             * Varyanta özel görsel (isteğe bağlı). Doluysa: ürün sayfasında o
             * kombinasyon seçilince ana görsel olur, sepette ve siparişte
             * ürün kapağı yerine gösterilir. Boşsa renk galerisi / kapak.
             */
            $table->string('image')->nullable()->after('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
