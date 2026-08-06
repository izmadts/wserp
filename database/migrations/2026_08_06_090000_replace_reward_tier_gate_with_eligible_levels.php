<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the minimum-tier-and-above gate with an exact list of eligible
 * tiers (e.g. "Gold only, not Platinum") - confirmed with the owner that a
 * reward's tier restriction should not assume higher tiers always inherit
 * lower ones. No production data exists for the old column yet (Golden
 * Club hasn't shipped past isolated QA copies), so this replaces rather
 * than converts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn('minimum_membership_level');
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->json('eligible_membership_levels')->nullable()->after('reward_type');
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn('eligible_membership_levels');
        });

        Schema::table('rewards', function (Blueprint $table) {
            $table->enum('minimum_membership_level', ['silver', 'gold', 'platinum'])->nullable()->after('reward_type');
        });
    }
};
