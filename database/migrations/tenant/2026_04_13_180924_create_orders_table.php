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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('user_id');
            
            $table->enum('status', [
                'pending', // Order placed, awaiting payment
                'paid', // Payment confirmed
                'processing', // Seller is preparing the order
                'shipped', // Order dispatched
                'delivered', // Order received by buyer
                'cancelled', // Order cancelled
                'refunded', // Payment refunded
            ])->default('pending');
            
            // Total amount at time of purchase
            $table->decimal('total_amount', 10, 2);
            
            // Shipping address stored as JSON snapshot
            $table->json('shipping_address');
            
            // Optional buyer notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
