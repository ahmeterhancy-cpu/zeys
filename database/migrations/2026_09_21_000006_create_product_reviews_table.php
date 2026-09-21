<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /*
             * Yorum hesaba değil SİPARİŞE bağlı: yalnız teslim edilmiş
             * siparişin sahibi yorum yazabilir, misafir alışverişi de dahil.
             * Siparişte olmayan ürüne yorum yazılamaz, aynı siparişten aynı
             * ürüne ikinci yorum yazılamaz (benzersiz anahtar).
             */
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('author_name');           // "Ayşe Y." — tam ad yayımlanmaz
            $table->unsignedTinyInteger('rating');   // 1–5
            $table->string('title', 120)->nullable();
            $table->text('body');

            // pending | approved | rejected — onaysız yorum vitrinde görünmez
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'product_id']);
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
