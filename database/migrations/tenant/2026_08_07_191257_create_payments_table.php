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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('gateway'); // mercadopage|pagseguro
            $table->string('method'); // pix|boleto|card
            $table->string('gateway_id')->nullable(); // external transaction ID
            $table->string('gateway_status')->nullable(); // raw status from gateway
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending|paid|failed|refunded
            $table->json('gateway_response')->nullable(); // full gateway response
            $table->json('payment_details')->nullable(); // pix gr code, boleto url, etc
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // boleto/pix expiration
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
