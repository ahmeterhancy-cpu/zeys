<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_ogeleri', function (Blueprint $table) {
            $table->id();
            $table->string('konum')->index();    // ana | ust-serit | alt-yardim
            $table->string('etiket');
            $table->string('baglanti');          // /yol ya da https://…
            $table->boolean('yeni_sekme')->default(false);
            $table->unsignedInteger('sira')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_ogeleri');
    }
};
