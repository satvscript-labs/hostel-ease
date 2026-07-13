<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Razorpay client using the REST API directly (no SDK/composer dep):
 *  - createOrder() to open a Standard Checkout order,
 *  - verifySignature() to validate the callback (HMAC-SHA256).
 *
 * Credentials come from config/services.php (env RAZORPAY_KEY_ID / KEY_SECRET).
 */
class RazorpayService
{
    private const ORDERS_URL = 'https://api.razorpay.com/v1/orders';

    private const PAYMENTS_URL = 'https://api.razorpay.com/v1/payments';

    public function isConfigured(): bool
    {
        return (bool) config('services.razorpay.enabled')
            && (bool) config('services.razorpay.key')
            && (bool) config('services.razorpay.secret');
    }

    public function keyId(): ?string
    {
        return config('services.razorpay.key');
    }

    /**
     * Create a Razorpay order.
     *
     * @param  int  $amountPaise  Amount in paise (>= 100).
     * @return array{id:string,amount:int,currency:string,receipt:?string}
     *
     * @throws RuntimeException on auth failure or API error.
     */
    public function createOrder(int $amountPaise, string $receipt, array $notes = [], string $currency = 'INR'): array
    {
        if ($amountPaise < 100) {
            throw new RuntimeException('Amount must be at least 100 paise.');
        }
        if (! $this->isConfigured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        $response = Http::withBasicAuth(
            config('services.razorpay.key'),
            config('services.razorpay.secret'),
        )->acceptJson()->post(self::ORDERS_URL, [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => $notes,
        ]);

        if ($response->status() === 401) {
            throw new RuntimeException('Razorpay authentication failed.', 401);
        }
        if ($response->failed()) {
            $message = $response->json('error.description') ?? 'Razorpay order creation failed.';
            throw new RuntimeException($message, $response->status() ?: 500);
        }

        $data = $response->json();

        return [
            'id' => $data['id'],
            'amount' => (int) $data['amount'],
            'currency' => $data['currency'],
            'receipt' => $data['receipt'] ?? null,
        ];
    }

    /**
     * Fetch a captured payment from Razorpay for server-side verification
     * (authoritative amount/status/order_id — never trust the client for these).
     *
     * @return array{id:string,amount:int,currency:string,status:string,order_id:?string}
     *
     * @throws RuntimeException on auth failure or API error.
     */
    public function fetchPayment(string $paymentId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        $response = Http::withBasicAuth(
            config('services.razorpay.key'),
            config('services.razorpay.secret'),
        )->acceptJson()->get(self::PAYMENTS_URL.'/'.$paymentId);

        if ($response->status() === 401) {
            throw new RuntimeException('Razorpay authentication failed.', 401);
        }
        if ($response->failed()) {
            $message = $response->json('error.description') ?? 'Razorpay payment fetch failed.';
            throw new RuntimeException($message, $response->status() ?: 500);
        }

        $data = $response->json();

        return [
            'id' => $data['id'] ?? $paymentId,
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'INR',
            'status' => $data['status'] ?? '',
            'order_id' => $data['order_id'] ?? null,
        ];
    }

    /**
     * Verify the checkout callback signature.
     * HMAC-SHA256(order_id + "|" + payment_id, KEY_SECRET) === razorpay_signature.
     */
    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = (string) config('services.razorpay.secret');
        if ($secret === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Verify a Razorpay webhook payload.
     * HMAC-SHA256(rawBody, WEBHOOK_SECRET) === X-Razorpay-Signature.
     */
    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) config('services.razorpay.webhook_secret');
        if ($secret === '' || $rawBody === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
