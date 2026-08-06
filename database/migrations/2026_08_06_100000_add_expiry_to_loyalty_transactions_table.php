<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Only ever set on type='earn' rows, at the moment points are credited -
 * the customer's membership tier at that exact moment decides the expiry
 * window (Settings > Golden Club > Points Expiry), and it is never
 * recomputed later even if the customer's tier changes afterwards. Indexed
 * because the nightly expiry command's whole job is scanning for rows
 * whose expires_at has just passed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->after('points')->index();
        });
    }

    public function down(): void
    {
        Schema::table('loyalty_transactions', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
