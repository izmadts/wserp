<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'free_delivery' and 'lucky_draw_entry' as real reward types
 * alongside the existing gift/coupon/product/discount - both need distinct
 * redemption handling (no stock/fulfillment for free_delivery, creates a
 * real LuckyDrawEntry on redemption for lucky_draw_entry) so they're
 * explicit enum values, not folded into 'coupon' via metadata. Raw ALTER,
 * same pattern already used in
 * 2026_08_05_170000_change_users_role_default_away_from_admin.php for
 * changing an enum column outside Laravel's fluent schema builder (which
 * has no built-in "add an enum option" operation).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rewards MODIFY COLUMN reward_type ENUM('gift', 'coupon', 'product', 'discount', 'free_delivery', 'lucky_draw_entry') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE rewards MODIFY COLUMN reward_type ENUM('gift', 'coupon', 'product', 'discount') NOT NULL");
    }
};
