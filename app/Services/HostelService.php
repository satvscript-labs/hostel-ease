<?php

namespace App\Services;

use App\Models\Hostel;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Provisions hostels for the Super Admin: creates the hostel, its first
 * subscription, and an auto-credentialed Hostel Admin login.
 */
class HostelService
{
    /**
     * @return array{hostel: Hostel, admin: User, password: string}
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $quote = app(\App\Services\BranchBillingService::class)->quote(new Hostel(), $data['plan'] ?? 'yearly');

            $hostel = Hostel::create([
                'name' => $data['name'],
                'owner_name' => $data['owner_name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'gst_number' => $data['gst_number'] ?? null,
                'subscription_start' => $quote['start'],
                'subscription_end' => $quote['end'],
                'status' => $data['status'] ?? 'active',
            ]);

            // If a login with this mobile already exists, treat it as the same
            // owner and just link the new hostel as another branch. Otherwise
            // create a fresh hostel-admin login.
            $existing = User::where('mobile', $hostel->mobile)->first();

            if ($existing) {
                if (! $existing->isHostelAdmin()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'mobile' => ['This mobile number belongs to another (non-admin) login.'],
                    ]);
                }
                $admin = $existing;
                $password = null; // existing owner keeps their current password
            } else {
                $password = $this->generatePassword();
                $admin = User::create([
                    'hostel_id' => $hostel->id,
                    'name' => $hostel->owner_name,
                    'mobile' => $hostel->mobile,        // login username
                    'email' => $hostel->email,
                    'password' => Hash::make($password),
                    'role' => 'hostel_admin',
                    'is_active' => true,
                ]);
            }

            // Grant this admin access to the new branch.
            $admin->hostels()->syncWithoutDetaching([$hostel->id]);

            $this->seedPaymentModes($hostel);

            app(\App\Services\BranchBillingService::class)->renewBranch($hostel, $data['plan'] ?? 'yearly', [
                'amount' => $data['amount'] ?? null,
                'payment_status' => $data['payment_status'] ?? 'pending',
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_number' => $data['transaction_number'] ?? null,
                'remarks' => 'Initial Branch Setup',
            ]);

            return ['hostel' => $hostel, 'admin' => $admin, 'password' => $password];
        });
    }



    /**
     * Seed the four default payment modes for a new hostel.
     */
    public function seedPaymentModes(Hostel $hostel): void
    {
        $defaults = [
            ['code' => 'cash', 'name' => 'Cash', 'requires_reference' => false],
            ['code' => 'upi', 'name' => 'UPI', 'requires_reference' => false],
            ['code' => 'cheque', 'name' => 'Cheque', 'requires_reference' => true],
            ['code' => 'rtgs', 'name' => 'RTGS / NEFT', 'requires_reference' => true],
        ];

        foreach ($defaults as $i => $d) {
            \App\Models\PaymentMode::firstOrCreate(
                ['hostel_id' => $hostel->id, 'code' => $d['code']],
                ['name' => $d['name'], 'requires_reference' => $d['requires_reference'], 'sort_order' => $i, 'is_active' => true],
            );
        }
    }

    public function resetPassword(User $user): string
    {
        $password = $this->generatePassword();
        $user->update(['password' => Hash::make($password)]);

        return $password;
    }

    protected function generatePassword(): string
    {
        return Str::upper(Str::random(4)).random_int(1000, 9999);
    }
}

