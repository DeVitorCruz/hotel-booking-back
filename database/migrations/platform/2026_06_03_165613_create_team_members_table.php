<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // leader | member
            $table->string('role')->default('member');
            $table->timestamp('joined_at')->useCurrent();
            // null = still active member
            $table->timestamp('left_at')->nullable();
            $table->string('status')->default('active'); // active|suspended|left
            $table->text('notes')->nullable();
            $table->timestamps();

            // A user can only have one active record per team

            $table->unique(['team_id', 'user_id'], 'unique_team_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
