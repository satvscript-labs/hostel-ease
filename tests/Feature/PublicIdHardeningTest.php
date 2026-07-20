<?php

namespace Tests\Feature;

use App\Models\AcBill;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PocketMoneyTransaction;
use App\Models\SecurityDeposit;
use App\Models\Staff;
use App\Models\StaffSalaryPayment;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentRegistration;
use Tests\TestCase;

/**
 * The invariant guard for the public-id hardening workstream
 * (_artifact/public_id_hardening/00_plan.md).
 *
 * Per-model behaviour is covered in each module's own test; this one exists so
 * that a model can never QUIETLY lose (or never gain) its opaque route key. The
 * failure it catches is the invisible one: drop the trait and nothing errors —
 * URLs just silently revert to leaking sequential integers again.
 */
class PublicIdHardeningTest extends TestCase
{
    /** Every table migrated so far, tier by tier. Add to this list as U3+ land. */
    public static function migratedModels(): array
    {
        return [
            // U0
            'Student' => [Student::class],
            // U1 — personal data
            'Staff' => [Staff::class],
            'StudentRegistration' => [StudentRegistration::class],
            'StudentDocument' => [StudentDocument::class],
            // U2 — financial
            'Invoice' => [Invoice::class],
            'Payment' => [Payment::class],
            'Expense' => [Expense::class],
            'SecurityDeposit' => [SecurityDeposit::class],
            'AcBill' => [AcBill::class],
            'PocketMoneyTransaction' => [PocketMoneyTransaction::class],
            'StaffSalaryPayment' => [StaffSalaryPayment::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('migratedModels')]
    public function test_a_migrated_model_routes_by_its_opaque_public_id(string $model): void
    {
        $instance = new $model;

        $this->assertSame(
            'public_id',
            $instance->getRouteKeyName(),
            $model.' lost its opaque route key — its URLs are leaking sequential integer ids again.'
        );

        // The PK stays an auto-incrementing integer: this is additive hardening,
        // never a primary-key change (every FK in the app depends on that).
        $this->assertSame('id', $instance->getKeyName());
        $this->assertTrue($instance->getIncrementing());
    }
}
