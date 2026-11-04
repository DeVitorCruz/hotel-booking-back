<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_spotlights', function (Blueprint $table) {
            $table->id();
            // Optional link to a real user account
            // null = static/manual entry added by owner
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Displayed info (can differ from user account data)
            $table->string('name');
            $table->string('role_title'); // "Lead Developer", "Support Manager"
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->unsignedTinyInteger('display_order')->default(0);
            // Owner controls visibility individually
            $table->boolean('is_visible')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
       Schema::dropIfExists('staff_spotlights');
    }
};
