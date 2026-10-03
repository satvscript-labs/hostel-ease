<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Razorpay client using the REST API directly (no SDK/composer dep):
 *  - createOrder() / fetchPayment() / verifySignature() — Standard Checkout (Phase 6),
 *  - createPaymentLink() and friends — operator-initiated collection (S2),
 *  - verifyWebhook() to validate server-to-server deliveries.
 *
 * Credentials come from config/services.php (env RAZORPAY_KEY_ID / KEY_SECRET).
 *
 * Every call carries an explicit TIMEOUT. That is not tidiness: link creation runs
 * inside the DB transaction that creates the order it collects for (so a failed
 * call leaves no orphan order behind — 10_S2_DESIGN.md §6 BP4), and a hung
 * Razorpay must not be able to hold that transaction open indefinitely.
 */
class RazorpayService
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    private const TIMEOUT_SECONDS = 15;

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

        $data = $this->send('post', '/orders', [
            'amount' => $amountPaise,
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => $notes,
        ], 'Razorpay order creation failed.');

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
        $data = $this->send('get', '/payments/'.$paymentId, [], 'Razorpay payment fetch failed.');

        return [
            'id' => $data['id'] ?? $paymentId,
            'amount' => (int) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'INR',
            'status' => $data['status'] ?? '',
            'order_id' => $data['order_id'] ?? null,
        ];
    }

    // -----------------------------------------------------------------
    // Payment Links (S2) — operator-initiated collection
    // -----------------------------------------------------------------

    /**
     * Create a hosted payment link for an arbitrary amount.
     *
     * Verified against Razorpay's live docs 2026-10-03 (10_S2_DESIGN.md §3):
     * amount in paise with a 100 minimum, `reference_id` unique per link and at
     * most 40 chars, `expire_by` a Unix timestamp at least 15 minutes ahead and at
     * most 6 months out, `notes` at most 15 pairs.
     *
     * `accept_partial` is deliberately NOT sent (it defaults to false). A
     * half-paid renewal is a support conversation, not a coverage decision — and
     * a partial payment would otherwise reach us as a link we must refuse to apply.
     *
     * No `callback_url` either: Razorpay's own receipt page is the destination, so
     * S2 needs no public unauthenticated route. (S3 adds the in-app return path.)
     *
     * @param  array{name?:?string, email?:?string, contact?:?string}  $customer
     * @param  array<string,string>  $notes
     * @return array{id:string, short_url:string, status:string, amount:int, reference_id:?string, expire_by:?int}
     *
     * @throws RuntimeException on auth failure, validation rejection or API error.
     */
    public function createPaymentLink(
        int $amountPaise,
        string $description,
        string $referenceId,
        array $customer = [],
        array $notes = [],
        ?int $expireBy = null,
        bool $notifySms = true,
        bool $notifyEmail = true,
        bool $remindersEnabled = true,
        string $currency = 'INR',
    ): array {
        if ($amountPaise < 100) {
            throw new RuntimeException('A payment link needs at least ₹1 — this charge is ₹0, so there is nothing to collect.');
        }

        // Only send the contact fields we actually hold. Razorpay rejects the whole
        // request for a malformed/empty email, and notifying by a channel we have no
        // address for is an error rather than a no-op (design §6 BP8).
        $customerBlock = array_filter([
            'name' => $customer['name'] ?? null,
            'email' => $customer['email'] ?? null,
            'contact' => $customer['contact'] ?? null,
        ], fn ($v) => filled($v));

        $payload = [
            'amount' => $amountPaise,
            'currency' => $currency,
            'description' => mb_substr($description, 0, 2048),
            'reference_id' => mb_substr($referenceId, 0, 40),
            'reminder_enable' => $remindersEnabled,
            'notify' => [
                'sms' => $notifySms && filled($customerBlock['contact'] ?? null),
                'email' => $notifyEmail && filled($customerBlock['email'] ?? null),
            ],
            'notes' => $this->trimNotes($notes),
        ];

        if ($customerBlock) {
            $payload['customer'] = $customerBlock;
        }
        if ($expireBy) {
            $payload['expire_by'] = $expireBy;
        }

        $data = $this->send('post', '/payment_links', $payload, 'Razorpay could not create the payment link.');

        return [
            'id' => (string) ($data['id'] ?? ''),
            'short_url' => (string) ($data['short_url'] ?? ''),
            'status' => (string) ($data['status'] ?? 'created'),
            'amount' => (int) ($data['amount'] ?? $amountPaise),
            'reference_id' => $data['reference_id'] ?? null,
            'expire_by' => isset($data['expire_by']) ? (int) $data['expire_by'] : null,
        ];
    }

    /**
     * Read a link's current state back from Razorpay.
     *
     * The `payments` array is the valuable part and the reason this exists: it
     * carries each captured `payment_id`, which is what lets the operator's
     * "Check with Razorpay" action APPLY a payment the webhook never delivered,
     * rather than merely report that one happened (design §6 BP6).
     *
     * @return array{id:string, status:string, amount:int, amount_paid:int, short_url:?string, reference_id:?string, expire_by:?int, payments:array<int, array{payment_id:?string, amount:int, status:?string, method:?string}>}
     *
     * @throws RuntimeException on auth failure or API error.
     */
    public function fetchPaymentLink(string $linkId): array
    {
        $data = $this->send('get', '/payment_links/'.$linkId, [], 'Razorpay payment link fetch failed.');

        return [
            'id' => (string) ($data['id'] ?? $linkId),
            'status' => (string) ($data['status'] ?? ''),
            'amount' => (int) ($data['amount'] ?? 0),
            'amount_paid' => (int) ($data['amount_paid'] ?? 0),
            'short_url' => $data['short_url'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'expire_by' => isset($data['expire_by']) ? (int) $data['expire_by'] : null,
            'payments' => collect($data['payments'] ?? [])
                ->map(fn ($p) => [
                    'payment_id' => $p['payment_id'] ?? null,
                    'amount' => (int) ($p['amount'] ?? 0),
                    'status' => $p['status'] ?? null,
                    'method' => $p['method'] ?? null,
                ])
                ->all(),
        ];
    }

    /**
     * Cancel a live link so it can never take money again.
     *
     * Razorpay refuses this for a link that is already paid, expired or cancelled.
     * That is an expected outcome, not an exception the caller should swallow
     * blindly — PaymentLinkService catches it and reconciles the local state
     * instead, so the operator's action never fails because of upstream state
     * they cannot control (design §6 BP2).
     *
     * @return array{id:string, status:string}
     *
     * @throws RuntimeException when Razorpay refuses.
     */
    public function cancelPaymentLink(string $linkId): array
    {
        $data = $this->send('post', '/payment_links/'.$linkId.'/cancel', [], 'Razorpay could not cancel the payment link.');

        return [
            'id' => (string) ($data['id'] ?? $linkId),
            'status' => (string) ($data['status'] ?? 'cancelled'),
        ];
    }

    /**
     * Ask Razorpay to re-send a live link by SMS or email — the free nudge.
     *
     * @param  string  $medium  'sms' or 'email'
     *
     * @throws RuntimeException on auth failure or API error.
     */
    public function notifyPaymentLink(string $linkId, string $medium = 'sms'): bool
    {
        $medium = in_array($medium, ['sms', 'email'], true) ? $medium : 'sms';

        $data = $this->send(
            'post',
            '/payment_links/'.$linkId.'/notify_by/'.$medium,
            [],
            'Razorpay could not re-send the payment link.',
        );

        return (bool) ($data['success'] ?? true);
    }

    // -----------------------------------------------------------------
    // Signatures
    // -----------------------------------------------------------------

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

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * One authenticated call, with one error contract for every endpoint. The
     * three API methods above each repeated this block; a divergence between
     * copies is the kind of thing that only shows up when a payment fails.
     *
     * @throws RuntimeException
     */
    private function send(string $method, string $path, array $payload, string $fallbackMessage): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        $response = $this->client()->{$method}(self::BASE_URL.$path, $payload);

        if ($response->status() === 401) {
            throw new RuntimeException('Razorpay authentication failed.', 401);
        }
        if ($response->failed()) {
            $message = $response->json('error.description') ?? $fallbackMessage;
            throw new RuntimeException($message, $response->status() ?: 500);
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('services.razorpay.key'),
            (string) config('services.razorpay.secret'),
        )->acceptJson()->timeout(self::TIMEOUT_SECONDS);
    }

    /**
     * Razorpay allows at most 15 note pairs of at most 256 chars each, and rejects
     * the request if either is exceeded. Clamp rather than fail a real payment on a
     * long remark.
     *
     * @param  array<string,mixed>  $notes
     * @return array<string,string>
     */
    private function trimNotes(array $notes): array
    {
        return collect($notes)
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v) => mb_substr((string) $v, 0, 256))
            ->take(15)
            ->all();
    }
}
