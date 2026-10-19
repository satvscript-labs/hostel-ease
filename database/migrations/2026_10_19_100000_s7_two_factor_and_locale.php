<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S7.1 (_artifact/saas_billing_autopay/34_S7_1_DESIGN.md): each person's language, and
 * two-factor sign-in.
 *
 *  - users.locale                     en | hi | gu — null = the app default
 *  - users.two_factor_method          null (off) | app | email
 *  - users.two_factor_secret          the authenticator key, encrypted (app only)
 *  - users.two_factor_recovery_codes  encrypted JSON list of HASHED one-time codes
 *  - users.two_factor_confirmed_at    when it was switched on
 *  - users.two_factor_last_step       the last 30-second step an app code was used for (no replay)
 *  - trusted_devices                  "trust this device for 30 days" — only a hash of the cookie token
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('email_verified_at');
            $table->string('two_factor_method', 10)->nullable()->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_method');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });

        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label', 150)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'two_factor_method', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};
