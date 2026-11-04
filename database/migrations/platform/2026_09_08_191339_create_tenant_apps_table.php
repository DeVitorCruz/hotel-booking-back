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
        Schema::create('tenant_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_type_id')->constrained();
            $table->foreignId('theme_id')->constrained();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('db_name')->unique(); // mercatura_tenant_{id}
            $table->string('status')->default('provisioning'); // provisioning|active|suspended|failed
            $table->json('settings')->nullable(); // app-specific config
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_apps');
    }
};
