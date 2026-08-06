<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Golden Club Fields
            if (!Schema::hasColumn('customers', 'membership_level')) {
                $table->enum('membership_level', ['silver', 'gold', 'platinum'])->default('silver')->after('is_active');
            }
            if (!Schema::hasColumn('customers', 'loyalty_points')) {
                $table->decimal('loyalty_points', 15, 2)->default(0)->after('membership_level');
            }
            if (!Schema::hasColumn('customers', 'lucky_draw_entries')) {
                $table->integer('lucky_draw_entries')->default(0)->after('loyalty_points');
            }
            if (!Schema::hasColumn('customers', 'registration_source')) {
                $table->string('registration_source')->nullable()->after('lucky_draw_entries');
            }
            if (!Schema::hasColumn('customers', 'otp_verified')) {
                $table->boolean('otp_verified')->default(false)->after('registration_source');
            }
            if (!Schema::hasColumn('customers', 'club_join_date')) {
                $table->timestamp('club_join_date')->nullable()->after('otp_verified');
            }
            if (!Schema::hasColumn('customers', 'total_purchase')) {
                $table->decimal('total_purchase', 15, 2)->default(0)->after('club_join_date');
            }
            if (!Schema::hasColumn('customers', 'lifetime_purchase')) {
                $table->decimal('lifetime_purchase', 15, 2)->default(0)->after('total_purchase');
            }
            if (!Schema::hasColumn('customers', 'last_purchase')) {
                $table->timestamp('last_purchase')->nullable()->after('lifetime_purchase');
            }
            if (!Schema::hasColumn('customers', 'customer_rank')) {
                $table->integer('customer_rank')->default(0)->after('last_purchase');
            }
            if (!Schema::hasColumn('customers', 'shop_name')) {
                $table->string('shop_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('customers', 'gps_location')) {
                $table->string('gps_location')->nullable()->after('address');
            }
            if (!Schema::hasColumn('customers', 'shop_picture')) {
                $table->string('shop_picture')->nullable()->after('gps_location');
            }
            if (!Schema::hasColumn('customers', 'category')) {
                $table->string('category')->nullable()->after('shop_picture');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'membership_level',
                'loyalty_points',
                'lucky_draw_entries',
                'registration_source',
                'otp_verified',
                'club_join_date',
                'total_purchase',
                'lifetime_purchase',
                'last_purchase',
                'customer_rank',
                'shop_name',
                'gps_location',
                'shop_picture',
                'category'
            ]);
        });
    }
};
