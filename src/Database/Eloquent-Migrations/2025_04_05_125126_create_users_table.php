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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // Email verification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
            $table->timestamp('email_verification_expires_at')->nullable();

            // Password reset
            $table->string('password_reset_token')->nullable();
            $table->timestamp('password_reset_expires_at')->nullable();
            $table->timestamp('password_reset_created_at')->nullable();

            // Remember me
            $table->string('remember_token')->nullable();

            $table->index(['email_verification_token']);
            $table->index(['password_reset_token']);
            $table->index(['remember_token']);
            $table->index(['email_verified_at']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
