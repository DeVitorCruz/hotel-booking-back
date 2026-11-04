<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant users table.
 * 
 * These are teh end-users of a tenant's apps (buyers, sellers, etc.).
 * Runs against the tenant DB connection (mercatura_tenant_{slug}).
 * Separate from the platform users (Mercatura staff / tenant owners).
 */
return new class extends Migration
{

    public function up(): void 
    {
        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // nullable for social-only accounts
            $table->string('phone', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('tenant')->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->unique();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void 
    {
        Schema::connection('tenant')->dropIfExists('sessions');
        Schema::connection('tenant')->dropIfExists('password_reset_tokens');
        Schema::connection('tenant')->dropIfExists('users');
    }
};