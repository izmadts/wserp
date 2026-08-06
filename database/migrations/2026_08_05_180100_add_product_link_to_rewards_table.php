<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a 'gift'/'product' reward point at a real Product row instead of
 * being pure free-text - redemption then draws down the SAME
 * products.current_stock every sale already uses (with a real
 * StockMovement, reference_type='reward_redemption'), rather than a second
 * stock number nobody keeps in sync. Nullable: coupon/discount/
 * free_delivery/lucky_draw_entry rewards have no product to link, and even
 * a physical reward can still be entered free-text if the admin doesn't
 * want it tied to live inventory.
 *
 * minimum_membership_level gates a reward to Gold/Platinum members (e.g. a
 * Platinum-only event invitation) - null means available to everyone,
 * matching how every other "membership tier" concept in this app already
 * treats an absent/lower tier as "no restriction" rather than "blocked".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->enum('minimum_membership_level', ['silver', 'gold', 'platinum'])->nullable()->after('reward_type');
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['minimum_membership_level']);
        });
    }
};
