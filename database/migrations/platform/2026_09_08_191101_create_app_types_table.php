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
        Schema::create('app_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // E-commerce, Restaurant, Streamer
            $table->string('slug')->unique(); // ecommerce, restaurant, streamer
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // icon name or path
            $table->string('db_template')->nullable(); // template DB to clone
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_types');
    }
};
