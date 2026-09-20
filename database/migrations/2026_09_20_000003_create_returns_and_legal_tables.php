<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Yasal metinler SÜRÜMLÜ tutulur.
         *
         * 6502 sayılı kanun kapsamında müşterinin sipariş anında NEYİ
         * onayladığı kanıtlanabilir olmalı. Metin sonradan değişirse eski
         * siparişin dayandığı sürüm okunabilir kalmalı — bu yüzden metin
         * üzerine yazılmaz, yeni sürüm eklenir.
         */
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            // mesafeli-satis | on-bilgilendirme | cayma-hakki | kvkk | cerez | iade-degisim
            $table->string('slug');
            $table->string('version');            // 2026-09-20.1
            $table->string('title');
            $table->longText('body');
            $table->boolean('is_current')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'version']);
            $table->index(['slug', 'is_current']);
        });

        /*
         * İade / değişim talebi.
         *
         * Konfeksiyonda iade istisna değil KURAL ("beden tutmadı"), bu yüzden
         * gerçek bir durum makinesi var. Değişim iadeden farklıdır: para geri
         * gitmez, başka bir varyant gönderilir.
         */
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();   // IAD-260920-0001
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('type')->default('return');   // return | exchange

            /*
             * opened → awaiting_shipment → received → approved | rejected
             * approved → completed
             * opened/awaiting_shipment → cancelled
             */
            $table->string('status')->default('opened');

            $table->string('reason');             // beden | kusurlu | yanlis-urun | vazgectim | diger
            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();

            // Müşterinin ürünü geri gönderdiği kargo
            $table->string('return_carrier')->nullable();
            $table->string('return_tracking_number')->nullable();

            // Değişimde yeni ürünün gittiği kargo
            $table->string('exchange_carrier')->nullable();
            $table->string('exchange_tracking_number')->nullable();

            $table->decimal('refund_amount', 10, 2)->default(0);

            $table->timestamp('shipped_back_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');

            // Değişimde müşterinin istediği yeni varyant (örn. bir beden büyüğü)
            $table->foreignId('exchange_variant_id')->nullable()
                ->constrained('product_variants')->nullOnDelete();

            $table->timestamps();

            $table->unique(['return_request_id', 'order_item_id'], 'return_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('legal_documents');
    }
};
