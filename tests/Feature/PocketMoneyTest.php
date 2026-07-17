<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Hostel;
use App\Models\PocketMoneyTransaction;
use App\Models\Student;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** First tests this module has ever had (W6.4). */
class PocketMoneyTest extends TestCase
{
    use RefreshDatabase;

    protected Hostel $hostel;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hostel = Hostel::factory()->create();
        $this->admin = User::factory()->create(['hostel_id' => $this->hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($this->hostel->id);
        $this->actingAs($this->admin);
    }

    protected function student(string $name = 'Amit', string $status = 'active'): Student
    {
        return Student::create(['hostel_id' => $this->hostel->id, 'name' => $name,
            'mobile' => '9'.rand(100000000, 999999999), 'occupation_type' => 'student', 'status' => $status]);
    }

    protected function tx(Student $s, string $type, float $amount): PocketMoneyTransaction
    {
        return PocketMoneyTransaction::create(['hostel_id' => $this->hostel->id, 'student_id' => $s->id,
            'type' => $type, 'amount' => $amount, 'created_by' => $this->admin->id]);
    }

    public function test_deposit_and_withdraw_move_the_balance(): void
    {
        $student = $this->student();

        $this->post(route('admin.pocket-money.store', $student), ['type' => 'deposit', 'amount' => 2000])
            ->assertSessionHas('success');
        $this->post(route('admin.pocket-money.store', $student), ['type' => 'withdraw', 'amount' => 500, 'note' => 'Canteen'])
            ->assertSessionHas('success');

        $this->assertEquals(1500.0, PocketMoneyTransaction::balanceFor($student->id));
    }

    /** Lending is allowed by design — a withdrawal can push the balance negative. */
    public function test_negative_balances_are_permitted(): void
    {
        $student = $this->student();
        $this->tx($student, 'deposit', 100);

        $this->post(route('admin.pocket-money.store', $student), ['type' => 'withdraw', 'amount' => 400])
            ->assertSessionHas('success');

        $this->assertEquals(-300.0, PocketMoneyTransaction::balanceFor($student->id));
    }

    /**
     * Owner decision (W6.4): a student who LEFT with money still in custody
     * stays on the list. The old page filtered to active students, so the
     * balance vanished from view while the total still counted it.
     */
    public function test_departed_student_with_a_balance_stays_visible(): void
    {
        $gone = $this->student('Gone Girl', 'left');
        $this->tx($gone, 'deposit', 750);

        $response = $this->get(route('admin.pocket-money.index'))->assertOk();
        $students = $response->viewData('students');

        $row = collect($students->items())->firstWhere('id', $gone->id);
        $this->assertNotNull($row, 'Departed student with custody money must stay listed');
        $this->assertEquals(750.0, $row->pocket_balance);
        $this->assertSame('left', $row->status);

        // And the totals agree with what is visible.
        $this->assertEquals(750.0, $response->viewData('totals')['custody']);
    }

    /** A departed student with a ZERO balance is not resurrected onto the list. */
    public function test_departed_student_with_zero_balance_stays_hidden(): void
    {
        $gone = $this->student('Settled Leaver', 'left');
        $this->tx($gone, 'deposit', 500);
        $this->tx($gone, 'withdraw', 500);

        $students = $this->get(route('admin.pocket-money.index'))->assertOk()->viewData('students');

        $this->assertNull(collect($students->items())->firstWhere('id', $gone->id));
    }

    public function test_index_searches_server_side(): void
    {
        $this->student('Findable Person');
        $this->student('Someone Else');

        $students = $this->get(route('admin.pocket-money.index', ['search' => 'findable']))
            ->assertOk()->viewData('students');

        $this->assertCount(1, $students->items());
        $this->assertSame('Findable Person', $students->items()[0]->name);
    }

    public function test_deleting_a_transaction_is_soft_and_audited(): void
    {
        $student = $this->student();
        $tx = $this->tx($student, 'deposit', 900);

        $this->delete(route('admin.pocket-money.destroy', [$student, $tx]))->assertSessionHas('success');

        $this->assertSoftDeleted('pocket_money_transactions', ['id' => $tx->id]);
        $this->assertEquals(0.0, PocketMoneyTransaction::balanceFor($student->id));
        $this->assertTrue(ActivityLog::where('action', 'pocket.delete')->exists(), 'Removal must be audited');
    }

    public function test_a_transaction_cannot_be_deleted_through_another_student(): void
    {
        $owner = $this->student('Owner');
        $other = $this->student('Other');
        $tx = $this->tx($owner, 'deposit', 900);

        $this->delete(route('admin.pocket-money.destroy', [$other, $tx]))->assertNotFound();
        $this->assertEquals(900.0, PocketMoneyTransaction::balanceFor($owner->id));
    }
}
