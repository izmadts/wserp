<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push-notification target for the Sale Agent app - the token FCM uses to
 * deliver to this user's device, and which platform sent it (rotates
 * whenever the OS/Firebase decides, so only the most recent value per user
 * is kept, not a history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fcm_token')->nullable()->after('remember_token');
            $table->string('fcm_token_platform')->nullable()->after('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'fcm_token_platform']);
        });
    }
};
