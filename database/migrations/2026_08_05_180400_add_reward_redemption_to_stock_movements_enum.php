<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * stock_movements.reference_type is an enum (widened once before, see
 * 2026_08_03_120000_widen_stock_movements_reference_type_enum.php) - a
 * Golden Club reward redemption that fulfills against a real linked
 * product needs its own value here, the same way sale/purchase/return/
 * adjustment/opening already each have one, or the insert is silently
 * truncated/rejected by MySQL instead of failing loudly.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY reference_type ENUM('purchase', 'purchase_return', 'sale', 'sales_return', 'return', 'adjustment', 'opening', 'reward_redemption')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE stock_movements MODIFY reference_type ENUM('purchase', 'purchase_return', 'sale', 'sales_return', 'return', 'adjustment', 'opening')");
    }
};
