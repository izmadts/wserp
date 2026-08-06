<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lucky_draw_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('minimum_purchase', 15, 2)->default(0);
            $table->json('entry_formula')->nullable(); // {"type": "fixed", "amount": 20000, "entries": 1}
            $table->json('prizes')->nullable(); // {"1st": "Bike", "2nd": "Mobile", "3rd": "Gift"}
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->integer('total_entries')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lucky_draw_campaigns');
    }
};
