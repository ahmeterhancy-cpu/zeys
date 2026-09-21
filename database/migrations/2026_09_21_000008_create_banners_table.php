<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();

            // slayt | afis (üçlü) | genis (ikili)
            $table->string('yer')->index();

            $table->string('ust_metin')->nullable();      // "Yeni koleksiyon" — el yazısı satırı
            $table->string('baslik');
            $table->string('alt_metin', 300)->nullable();

            $table->string('dugme_metni')->nullable();
            $table->string('baglanti')->nullable();       // /koleksiyon/yaz-26 ya da tam adres

            $table->string('gorsel')->nullable();
            $table->string('metin_konumu')->default('sag'); // slaytta metin kutusu: sol | sag

            // Kampanya takvimi: boşsa süresiz
            $table->timestamp('baslangic')->nullable();
            $table->timestamp('bitis')->nullable();

            $table->unsignedInteger('sira')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
