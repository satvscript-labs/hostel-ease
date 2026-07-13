<?php

namespace Tests\Feature;

use App\Mail\SubscriptionReminderMail;
use App\Models\Hostel;
use App\Models\SubscriptionAccount;
use App\Models\SubscriptionReminderLog;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionLifecycleCommandTest extends TestCase
{
    use RefreshDatabase;

    protected static int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::clear();
        config()->set('hostelease.grace_days', 3);
        config()->set('hostelease.renewal_reminder_days', [30, 15, 7, 0]);
        Mail::fake();
    }

    protected function ownerWithBranch(\Carbon\Carbon $end, bool $withEmail = true): SubscriptionAccount
    {
        $seq = ++static::$seq;
        $mobile = '94444'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        $owner = User::factory()->create(['role' => 'hostel_admin', 'mobile' => $mobile, 'email' => $withEmail ? "owner{$seq}@example.com" : null]);
        $branch = Hostel::factory()->create(['mobile' => $mobile, 'status' => 'active', 'subscription_end' => $end]);
        $owner->hostels()->sync([$branch->id]);

        return SubscriptionAccount::create(['owner_id' => $owner->id, 'period' => 'yearly', 'status' => 'active', 'current_period_end' => $end]);
    }

    public function test_sends_an_upcoming_reminder_at_a_configured_window(): void
    {
        $account = $this->ownerWithBranch(now()->addDays(7));

        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();

        Mail::assertQueued(SubscriptionReminderMail::class, fn ($mail) => $mail->account->id === $account->id && $mail->kind === 'upcoming');
        $this->assertDatabaseHas('subscription_reminder_logs', ['account_id' => $account->id, 'window' => 'due_7', 'status' => 'sent']);
    }

    public function test_does_not_send_outside_a_configured_window(): void
    {
        $this->ownerWithBranch(now()->addDays(20));

        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('subscription_reminder_logs', 0);
    }

    public function test_grace_and_expired_each_send_once(): void
    {
        $grace = $this->ownerWithBranch(now()->subDay());
        $expired = $this->ownerWithBranch(now()->subDays(10));

        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();

        Mail::assertQueued(SubscriptionReminderMail::class, fn ($mail) => $mail->account->id === $grace->id && $mail->kind === 'grace');
        Mail::assertQueued(SubscriptionReminderMail::class, fn ($mail) => $mail->account->id === $expired->id && $mail->kind === 'expired');
    }

    public function test_missing_email_is_skipped_not_errored(): void
    {
        $account = $this->ownerWithBranch(now()->addDays(7), withEmail: false);

        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertDatabaseHas('subscription_reminder_logs', ['account_id' => $account->id, 'window' => 'due_7', 'status' => 'skipped_no_email']);
    }

    public function test_running_twice_does_not_duplicate_the_same_window(): void
    {
        $account = $this->ownerWithBranch(now()->addDays(7));

        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();
        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();

        Mail::assertQueuedCount(1);
        $this->assertSame(1, SubscriptionReminderLog::where('account_id', $account->id)->where('window', 'due_7')->count());
    }

    public function test_suspended_account_is_never_emailed(): void
    {
        $account = $this->ownerWithBranch(now()->subDays(10));
        $account->update(['status' => 'suspended']);

        $this->artisan('hostelease:process-subscription-lifecycle')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame('suspended', $account->fresh()->status->value, 'A suspended account must not be auto-recomputed away from suspended by the daily tick.');
    }
}
