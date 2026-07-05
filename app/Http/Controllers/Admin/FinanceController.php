<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\PaymentMode;
use App\Models\PocketMoney;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        // For the Finance Dashboard, we need:
        // 1. All invoices (Dues)
        // 2. All payments (Transactions)
        // 3. Pocket money ledger
        // 4. Payment modes

        $invoices = Invoice::with('student')
            ->orderByDesc('id')
            ->get();

        $payments = Payment::with('student', 'invoices')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->get();

        $paymentModes = PaymentMode::orderBy('name')->get();

        $students = Student::active()->orderBy('name')->get(['id', 'name', 'mobile']);

        return view('admin.finance.index', compact('invoices', 'payments', 'paymentModes', 'students'));
    }
}
