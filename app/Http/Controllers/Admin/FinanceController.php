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

        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->input('sort', 'id');
        $direction = $request->input('direction', 'desc');

        // Whitelist sort columns
        $validInvoiceSorts = ['id', 'created_at', 'amount', 'paid_amount', 'balance'];
        $validPaymentSorts = ['id', 'paid_on', 'amount'];
        $invoiceSort = in_array($sort, $validInvoiceSorts) ? $sort : 'id';
        $paymentSort = in_array($sort, $validPaymentSorts) ? $sort : 'paid_on';
        $direction = in_array($direction, ['asc', 'desc']) ? $direction : 'desc';

        $invoicesQuery = Invoice::with('student')->orderBy($invoiceSort, $direction);
        $paymentsQuery = Payment::with('student', 'invoices')->orderBy($paymentSort, $direction);

        if ($search) {
            $invoicesQuery->where(function($query) use ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%");
                })->orWhere('title', 'like', "%{$search}%");
            });

            $paymentsQuery->where(function($query) use ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%");
                })->orWhere('receipt_number', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $invoicesQuery->where('status', $status);
        }

        $invoices = $invoicesQuery->get();
        $payments = $paymentsQuery->get();

        $paymentModes = PaymentMode::orderBy('name')->get();

        $students = Student::active()->orderBy('name')->get(['id', 'name', 'mobile']);

        return view('admin.finance.index', compact('invoices', 'payments', 'paymentModes', 'students', 'search', 'status', 'sort', 'direction'));
    }
}
