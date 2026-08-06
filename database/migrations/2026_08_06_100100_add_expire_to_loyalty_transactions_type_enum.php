<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * loyalty_transactions.type is a raw enum (same gotcha as rewards.reward_type
 * and stock_movements.reference_type elsewhere in this codebase) - the
 * points-expiry command needs its own distinct type so an expired batch
 * shows up as "Expired" in a customer's history, not lumped into the
 * generic "adjustment" bucket.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE loyalty_transactions MODIFY COLUMN type ENUM('earn', 'redeem', 'bonus', 'adjustment', 'expire') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE loyalty_transactions MODIFY COLUMN type ENUM('earn', 'redeem', 'bonus', 'adjustment') NOT NULL");
    }
};
