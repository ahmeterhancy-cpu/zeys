<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Musterinin onayladigi On Bilgilendirme Formu surumu.
            // contract_version yalnizca mesafeli satis sozlesmesini tutuyordu.
            $table->string('preinfo_version')->nullable()->after('contract_version');
            // Sozlesme belgelerinin e-postayla iletildigi an (kanit)
            $table->timestamp('contract_sent_at')->nullable()->after('contract_ip');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['preinfo_version', 'contract_sent_at']);
        });
    }
};
