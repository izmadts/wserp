<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The code a customer shares to refer someone else - auto-generated on
 * creation (see Customer::boot(), same pattern as Sale::invoice_no /
 * SalesReturn::return_no). Previously the referral program had no way for
 * a customer to even have a code to share, which is why CustomerReferral
 * was 100% dead code - nothing anywhere could create one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['referral_code']);
        });
    }
};
