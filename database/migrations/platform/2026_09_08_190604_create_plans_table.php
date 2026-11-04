<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // free|starter|pro
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 8, 2)->default(0.00);
            $table->decimal('price_yearly', 8, 2)->default(0.00);
            $table->unsignedInteger('max_apps')->default(1);
            $table->unsignedInteger('max_products')->default(10);
            $table->unsignedInteger('max_domains')->default(1);
            $table->json('features')->nullable(); // extra feature flags
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
