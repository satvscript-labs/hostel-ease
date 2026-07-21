<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Models\DiscountRule;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Payment;
use App\Models\PaymentMode;
use App\Models\Room;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentRegistration;
use App\Models\Subscription;
use App\Models\SubscriptionAccount;
use App\Models\User;
use App\Services\BedAssignmentService;
use App\Services\BedGenerator;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the ONE class of public-id bug automated tests kept missing.
 *
 * Several screens don't put a model URL in the HTML at all — they hand an id to
 * Alpine (in a @click payload or a JS lookup map) and assemble the URL in the
 * browser. PHPUnit never executes that JS, so when those ids stayed integers
 * after the route key became opaque, the whole suite stayed green while five
 * real features were broken (super-admin owner-account link, sub-user edit,
 * branch rename, discount-rule edit, subscription edit) — three of them failing
 * *silently*, because the lookup missed and `if (!x) return;` swallowed it.
 *
 * These tests assert the opaque id actually reaches the rendered payload. They
 * are deliberately coarse (assertSee) — the point is to fail loudly if a payload
 * ever reverts to an integer id, not to test the JavaScript.
 */
class PublicIdJsPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Storage::fake('public');
        Tenant::clear();
    }

    // ── U0 + U3 — the Property Board ─────────────────────────────────────
    // The single riskiest page in the workstream: student, bed AND assignment
    // URLs are all assembled in Alpine, across three different tiers.

    public function test_property_board_emits_opaque_ids_but_keeps_the_posted_bed_id_an_integer(): void
    {
        $hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);

        $floor = Floor::create(['hostel_id' => $hostel->id, 'name' => 'Ground']);
        $room = Room::create(['hostel_id' => $hostel->id, 'floor_id' => $floor->id,
            'room_number' => '101', 'room_type' => 'non_ac', 'sharing' => 3, 'rent' => 5000]);
        app(BedGenerator::class)->sync($room);

        $beds = $room->beds()->orderBy('id')->get();
        $student = Student::create(['hostel_id' => $hostel->id, 'name' => 'Occupant',
            'mobile' => '9800000055', 'occupation_type' => 'student', 'status' => 'active']);

        app(BedAssignmentService::class)->assign($student, $beds[0], [
            'join_date' => now()->toDateString(), 'fee_amount' => 5000, 'fee_frequency' => 'monthly',
        ]);
        $assignment = $student->activeAssignment()->firstOrFail();

        // A third bed in maintenance so the openBedStatus() branch renders too.
        $beds[2]->update(['status' => 'maintenance']);

        $html = $this->actingAs($admin)->get(route('admin.property.index'))->assertOk()->getContent();

        // U3 — bed history + bed status URLs are built from this argument.
        $this->assertStringContainsString("openDetails('{$beds[0]->public_id}'", $html,
            'Occupied bed passes a non-opaque id — the Bed History link will 404.');
        $this->assertStringContainsString("openBedStatus('{$beds[2]->public_id}'", $html,
            'Maintenance bed passes a non-opaque id — the bed status form will 404.');

        // U3 — transfer/release actions are built from the assignment id.
        $this->assertStringContainsString($assignment->public_id, $html,
            'Bed payload lost assignment_public_id — transfer/release will 404.');

        // U0 — "View Full Profile" is built from the student id.
        $this->assertStringContainsString($student->public_id, $html,
            'Bed payload lost student_public_id — View Full Profile will 404.');

        // THE COUNTER-CHECK — the inverse mistake of everything above.
        // An EMPTY bed is referenced only by the assign payload, whose `bedId`
        // is POSTed as the `bed_id` form field: a DB reference validated
        // `exists:beds,id`, so it must stay an INTEGER. Asserting the empty
        // bed's OPAQUE id never reaches the page proves that, independently of
        // how Js::from happens to escape the JSON.
        $emptyBed = $beds[1];
        $this->assertSame('empty', $emptyBed->fresh()->status, 'Test setup: bed 2 should be free.');
        $this->assertStringNotContainsString($emptyBed->public_id, $html,
            'The assign payload went opaque — bed_id would fail exists:beds,id and assignment breaks silently.');
    }

    // ── U1 — registrations, staff, expenses ──────────────────────────────

    /** The review modal builds approve / reject / aadhaar URLs from this one id. */
    public function test_registrations_review_payload_carries_the_opaque_id(): void
    {
        $hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);

        $reg = StudentRegistration::create([
            'hostel_id' => $hostel->id, 'name' => 'Applicant', 'mobile' => '+919800000077',
            'father_mobile' => '+919800000078', 'aadhaar' => '111122223333', 'address' => '1 Rd',
            'city' => 'Surat', 'state' => 'Gujarat', 'occupation_type' => 'working',
            'joining_date' => now()->toDateString(), 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.registrations.index'))
            ->assertOk()
            ->assertSee("id: '{$reg->public_id}'", false);
    }

    /** Restore lives on a SOFT-DELETED staff member — its route binds withTrashed. */
    public function test_removed_staff_row_builds_its_restore_url_from_the_opaque_id(): void
    {
        $hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);

        $staff = Staff::create(['hostel_id' => $hostel->id, 'name' => 'Gone Guard',
            'designation' => 'Guard', 'mobile' => '9800000066', 'monthly_salary' => 9000, 'is_active' => true]);
        $staff->delete();

        $this->actingAs($admin)->get(route('admin.staff.index', ['status' => 'removed']))
            ->assertOk()
            ->assertSee(route('admin.staff.restore', $staff), false);
    }

    /**
     * The salary-mirror expense links to the staff member — and must keep doing
     * so after they are REMOVED (the profile stays reachable by design). That
     * required eager-loading the staff relation withTrashed; without it the
     * relation returns null and the link silently disappears.
     */
    public function test_expenses_link_a_salary_mirror_to_its_staff_even_once_removed(): void
    {
        $hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);
        PaymentMode::create(['hostel_id' => $hostel->id, 'code' => 'cash', 'name' => 'Cash',
            'is_active' => true, 'requires_reference' => false, 'sort_order' => 0]);

        $staff = Staff::create(['hostel_id' => $hostel->id, 'name' => 'Paid Then Removed',
            'designation' => 'Cook', 'mobile' => '9800000067', 'monthly_salary' => 12000, 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.staff.salary', $staff), [
            'salary_month' => now()->format('Y-m'), 'amount' => 12000,
            'paid_on' => now()->toDateString(), 'mode' => 'cash',
        ])->assertRedirect();

        $staff->delete();

        $this->actingAs($admin)->get(route('admin.expenses.index'))
            ->assertOk()
            ->assertSee(route('admin.staff.show', $staff), false);
    }

    // ── U2 — the receipt link on the transactions list ───────────────────

    public function test_transactions_list_links_receipts_by_opaque_id(): void
    {
        $hostel = Hostel::factory()->create();
        $admin = User::factory()->create(['hostel_id' => $hostel->id, 'role' => 'hostel_admin']);
        Tenant::set($hostel->id);
        PaymentMode::create(['hostel_id' => $hostel->id, 'code' => 'cash', 'name' => 'Cash',
            'is_active' => true, 'requires_reference' => false, 'sort_order' => 0]);

        $student = Student::create(['hostel_id' => $hostel->id, 'name' => 'Payer',
            'mobile' => '9800000068', 'occupation_type' => 'student', 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.students.collect', $student), [
            'amount' => 1500, 'mode' => 'cash', 'paid_on' => now()->toDateString(),
        ])->assertRedirect();

        $payment = Payment::firstOrFail();

        $this->actingAs($admin)->get(route('admin.finance.index', ['tab' => 'transactions']))
            ->assertOk()
            ->assertSee(route('admin.payments.pdf', $payment), false);
    }

    /** Settings: the sub-user edit URL and the branch rename URL are both JS-built. */
    public function test_settings_page_emits_opaque_ids_for_user_edit_and_branch_rename(): void
    {
        $branch = Hostel::factory()->create();
        $owner = User::factory()->create(['role' => 'hostel_admin', 'hostel_id' => $branch->id]);
        $owner->hostels()->sync([$branch->id]);
        $branch->forceFill(['owner_id' => $owner->id])->save();

        $member = User::factory()->create(['role' => 'manager', 'hostel_id' => $branch->id]);
        $member->hostels()->sync([$branch->id]);

        Tenant::set($branch->id);

        $html = $this->actingAs($owner)->get(route('admin.settings.index'))->assertOk()->getContent();

        // openUserModal() builds `/admin/users/<key>` from the payload's public_id.
        $this->assertStringContainsString($member->public_id, $html,
            'Settings user payload lost its public_id — the Edit User URL will 404.');

        // openRenameModal() builds `/admin/branches/<key>/rename`.
        $this->assertStringContainsString($branch->public_id, $html,
            'Branch rename handler lost its public_id — rename will 404.');
    }

    /** Super-admin hostels list: the "Owner account" link is built from a mapped value. */
    public function test_superadmin_hostels_list_links_the_owner_account_by_opaque_id(): void
    {
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => '+919000000123']);
        $branch = Hostel::factory()->create(['mobile' => '+919000000123']);
        $owner->hostels()->sync([$branch->id]);
        $branch->forceFill(['owner_id' => $owner->id])->save();

        $account = SubscriptionAccount::create([
            'owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active',
            'current_period_end' => now()->addYear(),
        ]);

        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('superadmin.hostels.index'))
            ->assertOk()
            // The link target must be the account's opaque id, not its integer id.
            ->assertSee(route('superadmin.accounts.show', $account), false);

        $this->assertStringContainsString($account->public_id,
            route('superadmin.accounts.show', $account));
    }

    /** Discounts: openEdit() looks the rule up in a JS map, then builds the URL from the key. */
    public function test_superadmin_discounts_page_emits_opaque_rule_ids(): void
    {
        $rule = DiscountRule::create([
            'min_quantity' => 3, 'type' => DiscountType::Percentage->value,
            'value' => 10, 'active' => true,
        ]);

        $super = User::factory()->superAdmin()->create();

        $html = $this->actingAs($super)->get(route('superadmin.discounts.index'))
            ->assertOk()->getContent();

        // Must assert the LOOKUP MAP KEY, not merely that the id appears: the
        // toggle/delete form actions already put the public_id on this page, so
        // a bare assertSee() passes even when the map is keyed by integer and
        // the modal is broken. The key is the token immediately followed by the
        // rule's own fields.
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($rule->public_id, '/').'.{0,20}min_quantity/s',
            $html,
            'The rules lookup map is not keyed by public_id — openEdit() will miss and the modal will silently never open.'
        );
    }

    /** Subscriptions: same shape — a JS map whose key must match what openEditModal() gets. */
    public function test_superadmin_subscriptions_page_emits_opaque_subscription_ids(): void
    {
        $branch = Hostel::factory()->create();
        $subscription = Subscription::create([
            'hostel_id' => $branch->id, 'plan' => 'yearly',
            'start_date' => now()->subMonth(), 'end_date' => now()->addMonths(11),
            'amount' => 10000, 'payment_status' => 'paid',
        ]);

        $super = User::factory()->superAdmin()->create();

        $html = $this->actingAs($super)->get(route('superadmin.subscriptions.index'))
            ->assertOk()->getContent();

        // Same trap as the discounts map: the accept/delete form actions already
        // render the public_id, so only asserting the MAP KEY (the token
        // immediately followed by the record's fields) has any teeth.
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($subscription->public_id, '/').'.{0,20}plan/s',
            $html,
            'The subs lookup map is not keyed by public_id — openEditModal() will miss and the modal will silently never open.'
        );
    }
}
