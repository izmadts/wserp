<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            // Matches the `type` key FcmService already sends in the push
            // payload (see sale_confirmed/sale_rejected/payment_approved/
            // payment_rejected) - the app routes a tap using the same value.
            $table->string('type')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_notifications');
    }
};
