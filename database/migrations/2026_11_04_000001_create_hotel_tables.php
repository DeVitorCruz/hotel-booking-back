<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotel domain: settings, amenities, room types, rooms, rates and blocks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Key/value overrides for config/hotel.php (editable from the back office)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });

        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('max_adults')->default(2);
            $table->unsignedTinyInteger('max_children')->default(0);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->string('beds')->nullable();               // e.g. "1 King" / "2 Twin"
            $table->unsignedSmallInteger('size_sqm')->nullable();
            $table->decimal('base_price', 10, 2);             // per night, hotel currency
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('amenity_room_type', function (Blueprint $table) {
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->primary(['amenity_id', 'room_type_id']);
        });

        Schema::create('room_type_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();               // "101", "Garden Suite"
            $table->string('floor')->nullable();
            $table->string('status')->default('active');      // active | maintenance | inactive
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Seasonal / special prices. Highest priority wins when ranges overlap.
        Schema::create('rate_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();               // "High season", "New Year"
            $table->date('start_date');
            $table->date('end_date');                          // inclusive
            $table->decimal('price', 10, 2);
            $table->unsignedTinyInteger('min_nights')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
            $table->index(['room_type_id', 'start_date', 'end_date']);
        });

        // Dates a room cannot be sold (maintenance, owner use, ...)
        Schema::create('room_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');                          // exclusive, like check-out
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_blocks');
        Schema::dropIfExists('rate_overrides');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('room_type_images');
        Schema::dropIfExists('amenity_room_type');
        Schema::dropIfExists('room_types');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('settings');
    }
};
