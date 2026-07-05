<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcBill;
use App\Models\AcBillStudent;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\Complaint;
use App\Models\Expense;
use App\Models\Floor;
use App\Models\Hostel;
use App\Models\MonthlyRent;
use App\Models\PaymentMode;
use App\Models\Payment;
use App\Models\Room;
use App\Models\SemesterFee;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\Subscription;
use App\Models\Visitor;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Per-hostel data backup for the mobile app. Returns a single JSON document
 * containing every record belonging to the ACTIVE hostel only (tenant-scoped),
 * which the app saves onto the device. Other hostels' data is never included.
 */
class BackupController extends Controller
{
    public function export(): JsonResponse
    {
        $hid = Tenant::id();

        // BelongsToHostel models are auto-scoped to the active hostel.
        $data = [
            'meta' => [
                'app' => 'HSMS',
                'format_version' => 1,
                'hostel_id' => $hid,
                'generated_at' => now()->toIso8601String(),
            ],
            'hostel' => Hostel::find($hid),
            'subscriptions' => Subscription::where('hostel_id', $hid)->get(),
            'payment_modes' => PaymentMode::all(),
            'floors' => Floor::all(),
            'rooms' => Room::all(),
            'beds' => Bed::all(),
            'students' => Student::all(),
            'student_documents' => StudentDocument::all(),
            'bed_assignments' => BedAssignment::all(),
            'payments' => Payment::all(),
            'semester_fees' => SemesterFee::all(),
            'monthly_rents' => MonthlyRent::all(),
            'ac_bills' => AcBill::all(),
            'ac_bill_students' => AcBillStudent::all(),
            'expenses' => Expense::all(),
            'visitors' => Visitor::all(),
            'complaints' => Complaint::all(),
        ];

        $counts = [];
        foreach ($data as $k => $v) {
            if ($k === 'meta' || $k === 'hostel') {
                continue;
            }
            $counts[$k] = is_countable($v) ? count($v) : 0;
        }
        $data['meta']['counts'] = $counts;

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="hsms-backup.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
