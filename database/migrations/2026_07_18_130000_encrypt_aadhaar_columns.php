<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P5 — Aadhaar number at rest.
 *
 * The Aadhaar NUMBER (not the card image — that moved to the private disk in
 * P1–P4) was stored as plain text in three columns. India's DPDP Act 2023 treats
 * Aadhaar as sensitive personal data, so we encrypt it at rest via the models'
 * `encrypted` cast.
 *
 * Two things happen here, in order:
 *  1. Widen the columns to TEXT. Laravel's `encrypted` cast (AES-256-CBC) emits a
 *     base64 payload ~200+ chars long; the old string(12)/string(20) limits would
 *     silently TRUNCATE the ciphertext on MySQL and make it undecryptable.
 *  2. Backfill: encrypt any existing plaintext value in place. Done here (not a
 *     separate command) so there is no window where live code — which now carries
 *     the cast — reads a still-plaintext row and throws a DecryptException.
 *
 * The backfill is idempotent: a value that already decrypts is left alone, so a
 * re-run (or running after some rows were written through the cast) is safe.
 *
 * CAVEAT worth stating loudly: once encrypted, these values are recoverable ONLY
 * with the current APP_KEY. Rotating or losing APP_KEY makes them unreadable.
 *
 * The number is never searched, filtered, indexed, or unique anywhere in the app
 * (verified), so losing queryability to encryption breaks nothing.
 */
return new class extends Migration
{
    /** table => column */
    protected array $columns = [
        'staff' => 'aadhaar_number',
        'students' => 'aadhaar',
        'student_registrations' => 'aadhaar',
    ];

    public function up(): void
    {
        // 1 — widen so ciphertext fits (MySQL enforces length; SQLite ignores it
        // but the change still runs cleanly).
        Schema::table('staff', fn (Blueprint $t) => $t->text('aadhaar_number')->nullable()->change());
        Schema::table('students', fn (Blueprint $t) => $t->text('aadhaar')->nullable()->change());
        Schema::table('student_registrations', fn (Blueprint $t) => $t->text('aadhaar')->nullable()->change());

        // 2 — encrypt existing plaintext in place (idempotent).
        foreach ($this->columns as $table => $column) {
            $this->transform($table, $column, function (string $value) {
                // Already encrypted? Leave it.
                try {
                    Crypt::decryptString($value);

                    return null; // signal: no change
                } catch (\Throwable) {
                    return Crypt::encryptString($value);
                }
            });
        }
    }

    public function down(): void
    {
        // Decrypt back to plaintext first (so the values still fit the narrow
        // columns), then narrow the columns to their original widths.
        foreach ($this->columns as $table => $column) {
            $this->transform($table, $column, function (string $value) {
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    return null; // already plaintext — leave it
                }
            });
        }

        Schema::table('staff', fn (Blueprint $t) => $t->string('aadhaar_number')->nullable()->change());
        Schema::table('students', fn (Blueprint $t) => $t->string('aadhaar', 20)->nullable()->change());
        Schema::table('student_registrations', fn (Blueprint $t) => $t->string('aadhaar', 12)->nullable()->change());
    }

    /**
     * Walk every non-empty value in a column, applying $mutator. A null return
     * from $mutator means "leave this row untouched".
     */
    protected function transform(string $table, string $column, callable $mutator): void
    {
        DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column, $mutator) {
            foreach ($rows as $row) {
                $value = $row->{$column};
                if ($value === null || $value === '') {
                    continue;
                }
                $new = $mutator((string) $value);
                if ($new !== null && $new !== $value) {
                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                }
            }
        });
    }
};
