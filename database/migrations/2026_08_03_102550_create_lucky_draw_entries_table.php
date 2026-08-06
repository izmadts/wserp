<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lucky_draw_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->constrained('lucky_draw_campaigns')->onDelete('cascade');
            $table->foreignId('sale_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('purchase_amount', 15, 2);
            $table->integer('entry_count');
            $table->boolean('is_winner')->default(false);
            $table->string('prize')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'customer_id']);
            $table->index(['is_winner']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lucky_draw_entries');
    }
};
