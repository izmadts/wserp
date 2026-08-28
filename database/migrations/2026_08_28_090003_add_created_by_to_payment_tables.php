<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Neither the invoice-tied payment tables (purchase_payments) nor the
 * no-invoice direct payment tables (customer_payments/supplier_payments) have
 * ever recorded who entered a payment - added now so the dashboard's Recent
 * Payments widget can show "by" for all of them. sale_payments gets the same
 * column via 2026_08_28_090001 (bundled with its approval-status fields
 * there instead of here since that migration already touches the table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
