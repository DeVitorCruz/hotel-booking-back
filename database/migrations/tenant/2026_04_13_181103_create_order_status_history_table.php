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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->onDelete('cascade');
            
            $table->enum('status', [
				'pending',
				'paid',
				'processing',
				'shipped',
				'delivered',
				'cancelled',
				'refunded',
            ]);
            
            // Optional admin/seller comment on status change
            $table->string('comment')->nullable();
            
            // Who changed the status (admin, seller or system)
            $table->unsignedBigInteger('changed_by')
                  ->nullable();
           
            // Only created_at needed - history is immutable
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
