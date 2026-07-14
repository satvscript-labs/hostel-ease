<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P4 item 14 — admin/owner/branch-access robustness.
 *
 * Before this, "owner" was inferred two fragile ways (hostels.mobile match, or
 * "first hostel_admin in the hostel_user pivot") and Hostel::admins() read the
 * users.hostel_id FK while ACCESS read the pivot — so a multi-branch owner
 * appeared in the admin list of only their primary branch, and billing's
 * ownerForBranch() could pick a co-admin. This migration:
 *
 *  1. Adds hostels.owner_id — the ONE explicit owner (a hostel_admin User),
 *     aligned with subscription_accounts.owner_id.
 *  2. Backfills owner_id: mobile match first (the provision-time identity
 *     signal), else the first hostel_admin holding pivot access.
 *  3. Repairs pivot drift so access ⟺ pivot membership:
 *       - every user's primary hostel_id is in their pivot,
 *       - every owner holds pivot access to every branch they own.
 *  4. Gives owners with a NULL hostel_id (e.g. seeded logins) a primary branch.
 *  5. Retires the dead Role/Branch subsystem: users.role_id / users.branch_id
 *     columns and the never-seeded roles / branches tables (their models were
 *     unused; the API controller referencing them was already broken).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('mobile')
                ->constrained('users')->nullOnDelete();
        });

        // ── 2. Backfill owner_id ──
        $admins = DB::table('users')->where('role', 'hostel_admin')->whereNull('deleted_at')
            ->get(['id', 'mobile', 'hostel_id']);
        $byMobile = $admins->keyBy('mobile');
        $pivot = DB::table('hostel_user')->get()->groupBy('hostel_id');

        foreach (DB::table('hostels')->whereNull('deleted_at')->get(['id', 'mobile']) as $hostel) {
            $owner = $byMobile->get($hostel->mobile);

            if (! $owner) {
                // Fallback: first hostel_admin holding pivot access to this branch.
                $adminIds = $admins->pluck('id')->all();
                $ownerId = ($pivot->get($hostel->id) ?? collect())
                    ->pluck('user_id')->filter(fn ($id) => in_array($id, $adminIds, true))->sort()->first();
            } else {
                $ownerId = $owner->id;
            }

            if ($ownerId) {
                DB::table('hostels')->where('id', $hostel->id)->update(['owner_id' => $ownerId]);
            }
        }

        // ── 3a. Every user's primary hostel is in their pivot ──
        foreach (DB::table('users')->whereNotNull('hostel_id')->whereNull('deleted_at')->get(['id', 'hostel_id']) as $u) {
            DB::table('hostel_user')->insertOrIgnore([
                'hostel_id' => $u->hostel_id, 'user_id' => $u->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── 3b. Every owner holds pivot access to every branch they own ──
        foreach (DB::table('hostels')->whereNotNull('owner_id')->whereNull('deleted_at')->get(['id', 'owner_id']) as $h) {
            DB::table('hostel_user')->insertOrIgnore([
                'hostel_id' => $h->id, 'user_id' => $h->owner_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // ── 4. Owners with no primary branch get their first owned one ──
        foreach (DB::table('users')->where('role', 'hostel_admin')->whereNull('hostel_id')->whereNull('deleted_at')->get(['id']) as $u) {
            $first = DB::table('hostels')->where('owner_id', $u->id)->whereNull('deleted_at')->orderBy('id')->value('id')
                ?? DB::table('hostel_user')->where('user_id', $u->id)->orderBy('hostel_id')->value('hostel_id');
            if ($first) {
                DB::table('users')->where('id', $u->id)->update(['hostel_id' => $first]);
            }
        }

        // ── 5. Retire the dead Role/Branch subsystem ──
        if (Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('role_id');
            });
        }
        if (Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
        Schema::dropIfExists('branches');
        Schema::dropIfExists('roles');
    }

    public function down(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });

        // Recreate the (empty) retired structures so a rollback restores schema shape.
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['hostel_id', 'name']);
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
        });
    }
};
