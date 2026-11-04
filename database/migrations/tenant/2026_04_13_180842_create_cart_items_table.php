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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('cart_id')
                  ->constrained('carts')
                  ->onDelete('cascade');
                  
            // Links to the specific variant chosen (size, color, etc.)
            $table->foreignId('product_variant_id')
                  ->constrained('product_variants')
                  ->onDelete('cascade');
                  
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            
            // Prevent duplicate variant entries in the same cart
            $table->unique(['cart_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
