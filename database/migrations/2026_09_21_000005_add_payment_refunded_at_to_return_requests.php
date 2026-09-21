<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            // Paranin odeme kurulusu uzerinden GERCEKTEN iade edildigi an.
            // Doluysa "PayTR ile iade et" bir daha calismaz — cift iade kilidi.
            $table->timestamp('payment_refunded_at')->nullable()->after('refund_amount');
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            $table->dropColumn('payment_refunded_at');
        });
    }
};
