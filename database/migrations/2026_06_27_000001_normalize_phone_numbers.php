<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize all phone numbers to +91XXXXXXXXXX format across all tables.
 * Idempotent: safe to re-run. Only updates numbers that don't already have +91 prefix.
 */
return new class extends Migration {
    public function up(): void
    {
        // Normalize students table
        \DB::statement("
            UPDATE students SET mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
            WHERE mobile NOT LIKE '+91%' AND mobile IS NOT NULL AND mobile != ''
        ");
        \DB::statement("
            UPDATE students SET father_mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(father_mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
            WHERE father_mobile NOT LIKE '+91%' AND father_mobile IS NOT NULL AND father_mobile != ''
        ");
        \DB::statement("
            UPDATE students SET mother_mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(mother_mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
            WHERE mother_mobile NOT LIKE '+91%' AND mother_mobile IS NOT NULL AND mother_mobile != ''
        ");
        \DB::statement("
            UPDATE students SET guardian_mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(guardian_mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
            WHERE guardian_mobile NOT LIKE '+91%' AND guardian_mobile IS NOT NULL AND guardian_mobile != ''
        ");

        // Normalize staff table
        \DB::statement("
            UPDATE staff SET mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
            WHERE mobile NOT LIKE '+91%' AND mobile IS NOT NULL AND mobile != ''
        ");

        // Normalize visitors table
        \DB::statement("
            UPDATE visitors SET mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
            WHERE mobile NOT LIKE '+91%' AND mobile IS NOT NULL AND mobile != ''
        ");

        // Normalize users table (admins)
        \DB::statement("
            UPDATE users SET mobile = CONCAT('+91', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '+91', ''), '91', ''), '-', ''), ' ', ''), -10))
            WHERE mobile NOT LIKE '+91%' AND mobile IS NOT NULL AND mobile != ''
        ");
    }

    public function down(): void
    {
        // No rollback needed — normalization is one-way.
    }
};
