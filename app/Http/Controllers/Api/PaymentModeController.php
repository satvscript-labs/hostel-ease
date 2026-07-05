<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMode;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Full CRUD for payment modes. (Read for the collect form lives in
 * PaymentController@modes; this lists all incl. inactive for management.)
 */
class PaymentModeController extends Controller
{
    public function __construct(protected ActivityLogger $logger)
    {
    }

    public function index(): JsonResponse
    {
        $modes = PaymentMode::ordered()->get()->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'code' => $m->code,
            'requires_reference' => (bool) $m->requires_reference,
            'is_active' => (bool) $m->is_active,
        ]);

        return response()->json(['modes' => $modes]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);
        $mode = PaymentMode::create([
            'name' => $data['name'],
            'code' => $this->uniqueCode($data['name']),
            'requires_reference' => $request->boolean('requires_reference'),
            'is_active' => true,
            'sort_order' => (int) PaymentMode::max('sort_order') + 1,
        ]);
        $this->logger->log('payment_mode.create', "Added payment mode {$mode->name}");

        return response()->json(['message' => 'Payment mode added.', 'id' => $mode->id], 201);
    }

    public function update(Request $request, int $paymentMode): JsonResponse
    {
        $mode = PaymentMode::findOrFail($paymentMode);
        $data = $this->validatePayload($request);
        $mode->update([
            'name' => $data['name'],
            'requires_reference' => $request->boolean('requires_reference'),
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->logger->log('payment_mode.update', "Updated payment mode {$mode->name}", $mode);

        return response()->json(['message' => 'Payment mode updated.']);
    }

    public function destroy(int $paymentMode): JsonResponse
    {
        $mode = PaymentMode::findOrFail($paymentMode);
        $mode->delete();
        $this->logger->log('payment_mode.delete', "Deleted payment mode {$mode->name}");

        return response()->json(['message' => 'Payment mode removed.']);
    }

    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'requires_reference' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    protected function uniqueCode(string $name): string
    {
        $base = PaymentMode::makeCode($name);
        $code = $base;
        $i = 2;
        while (PaymentMode::where('code', $code)->exists()) {
            $code = $base.'_'.$i++;
        }

        return $code;
    }
}
