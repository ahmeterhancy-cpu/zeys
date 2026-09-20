<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();          // "Ev", "İş"
            $table->string('name');
            $table->string('phone');
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('district');                   // ilçe
            $table->string('city');                       // il
            $table->string('postal_code')->nullable();
            $table->boolean('is_default')->default(false);

            // Fatura için — kurumsal faturada unvan + VKN, bireyselde TCKN
            $table->string('invoice_type')->default('individual'); // individual | corporate
            $table->string('company_name')->nullable();
            $table->string('tax_office')->nullable();
            $table->string('tax_number')->nullable();     // VKN veya TCKN
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->default('percent');   // percent | amount
            $table->decimal('value', 10, 2);
            $table->decimal('min_total', 10, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();           // ZEY-260920-0001
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // misafir alışverişi

            // pending | paid | preparing | shipped | delivered | cancelled | refunded
            $table->string('status')->default('pending');
            // pending | paid | failed | refunded | partially_refunded
            $table->string('payment_status')->default('pending');

            /*
             * Stok durumu — aynı siparişte iki kez rezerve/düşüm olmasın diye.
             * none → reserved (ödeme başladı) → committed (ödeme onaylandı)
             * Ödeme düşerse reserved → none.
             */
            $table->string('stock_state')->default('none');

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');

            // Adresler sipariş anındaki hâliyle KOPYALANIR; müşteri sonradan
            // adresini değiştirince geçmiş sipariş bozulmasın.
            $table->json('shipping_address');
            $table->json('billing_address')->nullable();  // boşsa teslimat ile aynı

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->decimal('refunded_total', 10, 2)->default(0);

            $table->string('coupon_code')->nullable();

            // Ödeme
            $table->string('payment_provider')->nullable();   // paytr
            $table->string('payment_ref')->nullable();        // merchant_oid
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_meta')->nullable();

            // Kargo
            $table->string('shipping_carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            /*
             * 6502 sayılı kanun: sipariş anında onaylanan metinlerin SÜRÜMÜ
             * saklanır. Metin sonradan değişirse müşterinin neyi onayladığı
             * kanıtlanabilir olmalı.
             */
            $table->string('contract_version')->nullable();
            $table->timestamp('contract_accepted_at')->nullable();
            $table->string('contract_ip')->nullable();

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('payment_ref');
            $table->index('customer_email');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            /*
             * Ürün/varyant silinirse bağ kopar ama satır kalır — bu yüzden
             * ad, SKU ve varyant etiketi sipariş anında KOPYALANIR.
             * Referans projede varyant ADIYLA eşleştiriliyordu; matris
             * şemasında ad benzersiz değil, kimlik şart.
             */
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('variant_label')->nullable();  // "Siyah / M"
            $table->string('sku')->nullable();
            $table->string('image')->nullable();

            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->timestamps();

            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('addresses');
    }
};
