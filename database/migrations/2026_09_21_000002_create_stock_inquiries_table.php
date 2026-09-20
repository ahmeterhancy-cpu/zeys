<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Stokta yok — haber ver": belirli bir VARYANT icin bildirim istegi.
        // Urun degil varyant: musteri "Siyah M" bekliyor, "Siyah L" gelince
        // haber vermek yanlis olur.
        Schema::create('stock_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // Ayni kisi ayni varyant icin iki kez kayit olmasin
            $table->unique(['product_variant_id', 'email'], 'stock_inquiry_unique');
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_inquiries');
    }
};
