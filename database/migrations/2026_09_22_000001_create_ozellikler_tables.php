<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ortak özellik kütüphanesi (WooCommerce'teki "global attributes").
 *
 * Beden, Renk gibi özellikler ve değerleri bir kez tanımlanır; ürünler
 * buradan seçer. Ürün tarafındaki product_options / product_option_values
 * satırları kütüphaneye bağlanır (ozellik_id / ozellik_degeri_id) —
 * kütüphanede "Siyah" adı değişirse bütün ürünlerde değişir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ozellikler', function (Blueprint $table) {
            $table->id();
            $table->string('ad')->unique();              // Beden, Renk, Numara…
            $table->string('tur')->default('text');       // text | color
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();
        });

        Schema::create('ozellik_degerleri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ozellik_id')->constrained('ozellikler')->cascadeOnDelete();
            $table->string('deger');
            $table->string('renk_kodu')->nullable();
            $table->unsignedInteger('sira')->default(0);
            $table->timestamps();

            $table->unique(['ozellik_id', 'deger']);
        });

        Schema::table('product_options', function (Blueprint $table) {
            $table->foreignId('ozellik_id')->nullable()->after('product_id')->constrained('ozellikler')->nullOnDelete();
        });

        Schema::table('product_option_values', function (Blueprint $table) {
            $table->foreignId('ozellik_degeri_id')->nullable()->after('product_option_id')->constrained('ozellik_degerleri')->nullOnDelete();
        });

        $this->varsayilanlar();
        $this->mevcutUrunleriBagla();
    }

    /** Konfeksiyonda en sık kullanılanlar — panelden eklenip çıkarılabilir. */
    private function varsayilanlar(): void
    {
        $simdi = now();

        $beden = DB::table('ozellikler')->insertGetId(['ad' => 'Beden', 'tur' => 'text', 'sira' => 0, 'created_at' => $simdi, 'updated_at' => $simdi]);
        foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', '36', '38', '40', '42', '44', '46'] as $i => $d) {
            DB::table('ozellik_degerleri')->insert(['ozellik_id' => $beden, 'deger' => $d, 'sira' => $i, 'created_at' => $simdi, 'updated_at' => $simdi]);
        }

        $renk = DB::table('ozellikler')->insertGetId(['ad' => 'Renk', 'tur' => 'color', 'sira' => 1, 'created_at' => $simdi, 'updated_at' => $simdi]);
        foreach ([
            ['Siyah', '#1c1a15'], ['Beyaz', '#ffffff'], ['Ekru', '#f0e9dc'], ['Bej', '#d8c9ae'],
            ['Taş', '#c9bca6'], ['Kahverengi', '#6b4a32'], ['Lacivert', '#1e2a44'], ['Mavi', '#4a6fa5'],
            ['Gri', '#8c8c8c'], ['Antrasit', '#3b3b3b'], ['Kırmızı', '#a32b2b'], ['Bordo', '#6d1f2c'],
            ['Pembe', '#e8b4b8'], ['Yeşil', '#4f6b4a'], ['Haki', '#7a7152'], ['Sarı', '#e2c044'],
        ] as $i => [$d, $hex]) {
            DB::table('ozellik_degerleri')->insert(['ozellik_id' => $renk, 'deger' => $d, 'renk_kodu' => $hex, 'sira' => $i, 'created_at' => $simdi, 'updated_at' => $simdi]);
        }
    }

    /**
     * Var olan ürün eksenlerini kütüphaneye bağla: adı eşleşen özellik ya da
     * değer yoksa kütüphaneye eklenir. Hiçbir ürün verisi değişmez.
     */
    private function mevcutUrunleriBagla(): void
    {
        $simdi = now();

        foreach (DB::table('product_options')->get() as $eksen) {
            $ozellik = DB::table('ozellikler')->where('ad', $eksen->name)->first();
            $ozellikId = $ozellik?->id ?? DB::table('ozellikler')->insertGetId([
                'ad' => $eksen->name, 'tur' => $eksen->kind, 'sira' => 10, 'created_at' => $simdi, 'updated_at' => $simdi,
            ]);

            DB::table('product_options')->where('id', $eksen->id)->update(['ozellik_id' => $ozellikId]);

            foreach (DB::table('product_option_values')->where('product_option_id', $eksen->id)->get() as $deger) {
                $kutuphane = DB::table('ozellik_degerleri')->where('ozellik_id', $ozellikId)->where('deger', $deger->value)->first();
                $degerId = $kutuphane?->id ?? DB::table('ozellik_degerleri')->insertGetId([
                    'ozellik_id' => $ozellikId, 'deger' => $deger->value, 'renk_kodu' => $deger->color_hex,
                    'sira' => 100, 'created_at' => $simdi, 'updated_at' => $simdi,
                ]);

                DB::table('product_option_values')->where('id', $deger->id)->update(['ozellik_degeri_id' => $degerId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_option_values', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ozellik_degeri_id');
        });
        Schema::table('product_options', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ozellik_id');
        });
        Schema::dropIfExists('ozellik_degerleri');
        Schema::dropIfExists('ozellikler');
    }
};
