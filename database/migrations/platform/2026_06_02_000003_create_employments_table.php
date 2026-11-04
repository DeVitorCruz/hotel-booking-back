<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('role_name');
            $table->timestamp('hired_at')->useCurrent();
            $table->timestamp('fired_at')->nullable();
            $table->string('status')->default('active'); // active|suspended|terminated
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employer_id', 'employee_id', 'seller_id'], 'unique_employment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employments');
    }
};
