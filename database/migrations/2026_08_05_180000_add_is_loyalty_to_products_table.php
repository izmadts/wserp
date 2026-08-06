<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a product as eligible to be linked to a Golden Club reward (the
 * admin reward form filters its product picker to these) - mirrors
 * is_retail/is_wholesale (2026_08_04_090000_add_retail_wholesale_flags_to_products_table.php)
 * exactly, same reasoning: a simple boolean flag rather than a separate
 * join table, since a product either participates in the reward store or
 * it doesn't.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_loyalty')->default(false)->after('is_wholesale');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_loyalty']);
        });
    }
};
