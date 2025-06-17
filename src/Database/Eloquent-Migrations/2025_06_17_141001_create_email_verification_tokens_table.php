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
        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->string('email');
            $table->string('token')->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->index('email');
            // A primary key could be email + token if duplicates for an email are not allowed before expiry,
            // or an auto-incrementing id if preferred. For simplicity, using token as unique and email as indexed.
            // If email should be primary, use $table->primary('email'); and ensure tokens are managed accordingly.
            // For this iteration, let's assume one token per email at a time is enforced by application logic (delete old before new).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
    }
};
