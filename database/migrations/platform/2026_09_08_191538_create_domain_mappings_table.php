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
        Schema::create('domain_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_app_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique(); // full domain or subdomian
            $table->string('type')->default('subdomain'); // subdomain|custom
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_mappings');
    }
};
