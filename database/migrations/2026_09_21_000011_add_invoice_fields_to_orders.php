<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            /*
             * Fatura muhasebe programında / e-Arşiv portalında kesilir; burada
             * yalnız numarası, tarihi ve PDF'i tutulur. PDF kişisel veri içerir:
             * herkese açık diske DEĞİL, gizli diske (local) yazılır ve imzalı
             * bağlantıyla indirilir.
             */
            $table->string('invoice_number')->nullable()->after('contract_sent_at');
            $table->date('invoice_date')->nullable()->after('invoice_number');
            $table->string('invoice_pdf')->nullable()->after('invoice_date');
            $table->timestamp('invoice_sent_at')->nullable()->after('invoice_pdf');
            $table->index('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['invoice_number']);
            $table->dropColumn(['invoice_number', 'invoice_date', 'invoice_pdf', 'invoice_sent_at']);
        });
    }
};
