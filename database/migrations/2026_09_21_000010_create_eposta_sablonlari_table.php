<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eposta_sablonlari', function (Blueprint $table) {
            $table->id();
            $table->string('anahtar')->unique();   // App\Support\EpostaMetni::SABLONLAR anahtarı
            // Boş alan = koddaki varsayılan metin
            $table->string('konu')->nullable();
            $table->string('baslik')->nullable();
            $table->text('metin')->nullable();
            $table->text('not')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eposta_sablonlari');
    }
};
