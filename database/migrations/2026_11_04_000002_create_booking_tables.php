<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings, room-night occupancy (the overbooking guard) and payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();         // shown to the guest, e.g. HB7K2Q9M
            $table->text('manage_token');                       // encrypted; lets the guest manage the booking without an account
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();

            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone', 32)->nullable();
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->text('special_requests')->nullable();

            $table->date('check_in');
            $table->date('check_out');                         // exclusive
            $table->unsignedSmallInteger('nights');

            $table->string('currency', 3);
            $table->json('nightly_rates');                     // [{date, price}]
            $table->decimal('subtotal', 10, 2);
            $table->decimal('taxes', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('amount_due_now', 10, 2);          // deposit or full amount; 0 for pay at hotel

            // pending (hold) | confirmed | checked_in | checked_out | cancelled | expired | no_show
            $table->string('status')->default('pending');
            $table->string('payment_method');                 // paypal | pay_at_hotel
            $table->string('source')->default('web');          // web | admin
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'hold_expires_at']);
            $table->index(['check_in', 'check_out']);
            $table->index('guest_email');
        });

        /*
         * One row per room per night that is sold or blocked.
         * The unique (room_id, date) index is what makes double booking impossible,
         * even when two guests submit at the same moment.
         */
        Schema::create('room_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('room_block_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unique(['room_id', 'date']);
            $table->index('date');
        });

        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Polymorphic so other payable things (extras, invoices) can reuse it later.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->string('gateway');                         // paypal | pay_at_hotel
            $table->string('method');                          // paypal | cash_or_card_at_hotel
            $table->string('gateway_id')->nullable()->index(); // PayPal order ID
            $table->string('gateway_capture_id')->nullable()->index(); // PayPal capture ID (used for refunds)
            $table->string('gateway_status')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status')->default('pending');      // pending | paid | failed | refunded | partially_refunded
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->json('gateway_response')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('booking_status_history');
        Schema::dropIfExists('room_nights');
        Schema::dropIfExists('bookings');
    }
};
