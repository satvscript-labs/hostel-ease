<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcBillStudent;
use App\Models\MonthlyRent;
use App\Models\SemesterFee;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Records a "promise to pay" date + note on an unpaid obligation, so the admin
 * gets reminded when that date arrives.
 */
class PromiseController extends Controller
{
    protected array $types = [
        'semester_fee' => SemesterFee::class,
        'monthly_rent' => MonthlyRent::class,
        'ac_bill_student' => AcBillStudent::class,
    ];

    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset($this->types[$type]), 404);

        $data = $request->validate([
            'promise_date' => ['nullable', 'date'],
            'promise_note' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Model $record */
        $record = $this->types[$type]::findOrFail($id);   // tenant-scoped via global scope

        $record->forceFill([
            'promise_date' => $data['promise_date'] ?: null,
            'promise_note' => $data['promise_note'] ?? null,
        ])->save();

        $this->logger->log('promise.set',
            $data['promise_date'] ? "Promise to pay set for {$data['promise_date']}" : 'Promise cleared',
            $record);

        return back()->with('success', $data['promise_date'] ? 'Promise date saved.' : 'Promise cleared.');
    }
}
