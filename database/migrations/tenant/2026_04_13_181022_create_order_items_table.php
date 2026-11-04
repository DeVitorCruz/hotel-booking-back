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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->onDelete('cascade');
            
            // Keep reference to variant for stock management  
            $table->foreignId('product_variant_id')
                  ->nullable()
                  ->constrained('product_variants')
                  ->onDelete('set null');
                  
                  
            // Keep reference to seller for marketplace attribution
            $table->unsignedBigInteger('seller_id')
                  ->nullable();
                  
             // Snapshot of product data at purchase time
             // Preserves history even if product is later edited/deleted
             $table->string('product_name');
             $table->string('variant_sku');
             $table->decimal('unit_price', 10, 2);
             $table->unsignedInteger('quantity');
             $table->string('image_path')->nullable();
             
             // Snapshot of variant attributes (e.g. color: red, size: XL)
             $table->json('attributes')->nullable();
            
			 $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
