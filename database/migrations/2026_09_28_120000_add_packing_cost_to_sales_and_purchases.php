<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Packing/labor charge for a purchase or sale - a separate line from
     * shipping_cost (which already exists on both tables and this mirrors
     * exactly), since it's a distinct cost some invoices need to itemize.
     * Same treatment as shipping_cost: folded straight into total_amount
     * by Sale::calculateTotals()/Purchase::calculateTotals(), no separate
     * ledger account.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('packing_cost', 12, 2)->default(0)->after('shipping_cost');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('packing_cost', 12, 2)->default(0)->after('shipping_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('packing_cost');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('packing_cost');
        });
    }
};
