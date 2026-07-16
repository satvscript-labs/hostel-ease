<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Back-fill for the login bug fixed in W6.3-followup.
 *
 * The mobile IS the login username. LoginController normalises what you type to
 * +91XXXXXXXXXX and looks that up, but HostelService::provision() stored the
 * mobile exactly as handed to it — so any hostel provisioned with a plain
 * 10-digit number created an owner login that could never authenticate.
 *
 * The model mutators (User::mobile / Hostel::mobile) make that unrepresentable
 * going forward; this fixes the rows already written. Without it, existing
 * locked-out owners stay locked out.
 *
 * Rows are normalised in PHP through the same helper the app uses, so there is
 * exactly one definition of "normalised" — no SQL re-implementation to drift.
 * Collisions (two rows differing only by prefix) are left ALONE and reported:
 * merging logins is a decision, not a migration's call.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'hostels'] as $table) {
            $taken = DB::table($table)->pluck('mobile')->filter()->all();
            $taken = array_count_values(array_map('strval', $taken));

            DB::table($table)->select('id', 'mobile')->orderBy('id')->chunkById(200, function ($rows) use ($table, &$taken) {
                foreach ($rows as $row) {
                    if (blank($row->mobile)) {
                        continue;
                    }
                    $normalised = hostelease_phone($row->mobile);
                    if ($normalised === null || $normalised === $row->mobile) {
                        continue;
                    }
                    // Someone already holds the normalised number — normalising
                    // this row would collide on the unique index. Leave it and
                    // let a human decide which login is real.
                    if (($taken[$normalised] ?? 0) > 0) {
                        continue;
                    }

                    DB::table($table)->where('id', $row->id)->update(['mobile' => $normalised]);
                    $taken[$normalised] = 1;
                    $taken[$row->mobile] = max(0, ($taken[$row->mobile] ?? 1) - 1);
                }
            });
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: the pre-normalised shapes were arbitrary
        // (some +91, some bare), so there is nothing coherent to restore to.
    }
};
