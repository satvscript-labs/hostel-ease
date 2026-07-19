<?php

namespace App\Console\Commands;

use App\Models\Bed;
use App\Models\Hostel;
use App\Models\Student;
use App\Services\BedAssignmentService;
use App\Support\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Spin up throwaway student profiles for hands-on testing — every mandatory
 * field filled with a random-but-realistic placeholder (name, mobiles, a valid
 * 12-digit Aadhaar, address, occupation, join date), plus the sample ID card +
 * photo so the profile's Documents tab renders like a real one.
 *
 * Identity only by default (no bed, no plan, no invoices) — the freshest useful
 * state to then drive the assign / plan flows by hand. Pass --assign to also
 * drop each into a vacant bed with a plan (AC beds get a seeded meter reading).
 *
 *   php artisan hostelease:make-students 5
 *   php artisan hostelease:make-students 3 --hostel=2 --assign
 */
class MakeTestStudents extends Command
{
    protected $signature = 'hostelease:make-students
        {count=5 : How many students to create}
        {--hostel= : Hostel id (defaults to the first hostel)}
        {--assign : Also place each into a vacant bed with a fee plan}';

    protected $description = 'Create random test student profiles (all mandatory fields filled).';

    public function handle(BedAssignmentService $beds): int
    {
        $hostel = $this->option('hostel')
            ? Hostel::find((int) $this->option('hostel'))
            : Hostel::orderBy('id')->first();

        if (! $hostel) {
            $this->error('No hostel found. Seed one first (php artisan migrate:fresh --seed) or pass --hostel.');

            return self::FAILURE;
        }

        // The tenant scope drives creates/queries against this branch.
        Tenant::set($hostel->id);

        $count = max(1, (int) $this->argument('count'));
        $assign = (bool) $this->option('assign');

        // One shared, viewable sample card per hostel — so Documents shows a
        // real image, not a 404 (private disk, same shape a real upload takes).
        $card = "students/{$hostel->id}/aadhaar/test-sample.png";
        $photo = "students/{$hostel->id}/photos/test-sample.png";
        foreach ([$card, $photo] as $path) {
            if (! Storage::disk('private')->exists($path)) {
                Storage::disk('private')->put($path, $this->sampleImage());
            }
        }

        $made = collect();
        for ($i = 0; $i < $count; $i++) {
            $student = Student::factory()
                ->withDocuments($card, $photo)
                ->create(['hostel_id' => $hostel->id]);
            $made->push($student);
        }

        if ($assign) {
            $this->assignToVacantBeds($made, $beds);
        }

        $this->info("Created {$made->count()} student(s) in “{$hostel->name}”"
            .($assign ? ' and placed the ones that fit into vacant beds.' : '.'));
        foreach ($made as $s) {
            $this->line("  · {$s->name} — {$s->mobile}");
        }

        return self::SUCCESS;
    }

    /** Drop students into vacant beds with a plan; AC beds get a meter reading. */
    protected function assignToVacantBeds($students, BedAssignmentService $beds): void
    {
        foreach ($students as $student) {
            $bed = Bed::with('room')->whereIn('status', ['empty', 'available'])
                ->whereHas('room', fn ($q) => $q->where('hostel_id', $student->hostel_id))
                ->first();

            if (! $bed) {
                $this->warn('  (ran out of vacant beds)');

                return;
            }

            $data = [
                'join_date' => $student->join_date?->toDateString() ?? now()->toDateString(),
                'fee_amount' => (float) ($bed->room?->rent ?? 5000),
                'fee_frequency' => 'monthly',
            ];
            if ($bed->room?->isAc()) {
                // Above any existing floor for the room, so the meter guard is happy.
                $data['meter_reading'] = 100 + random_int(0, 400);
            }

            $beds->assign($student, $bed, $data);
            app(\App\Services\ProrationService::class)->generateInitialInvoice($student->refresh());
        }
    }

    /** A small labelled sample card (GD when present; 1×1 PNG fallback). */
    protected function sampleImage(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(440, 280);
            imagefilledrectangle($img, 0, 0, 440, 280, imagecolorallocate($img, 79, 70, 229));
            imagefilledrectangle($img, 20, 20, 420, 260, imagecolorallocate($img, 255, 255, 255));
            $muted = imagecolorallocate($img, 100, 116, 139);
            imagestring($img, 5, 40, 60, 'HOSTELEASE', imagecolorallocate($img, 15, 23, 42));
            imagestring($img, 4, 40, 110, 'TEST STUDENT DOCUMENT', $muted);
            imagestring($img, 3, 40, 170, 'Random placeholder - not real data', $muted);
            ob_start();
            imagepng($img);
            $bytes = (string) ob_get_clean();
            imagedestroy($img);

            return $bytes;
        }

        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }
}
