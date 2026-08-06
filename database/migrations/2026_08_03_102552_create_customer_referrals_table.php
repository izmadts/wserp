<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('referred_customer')->nullable();
            $table->string('referred_phone')->nullable();
            $table->enum('status', ['pending', 'registered', 'converted', 'rewarded'])->default('pending');
            $table->decimal('reward_points', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_referrals');
    }
};
