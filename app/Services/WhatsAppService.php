<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp messages via the configured Cloud API provider when enabled,
 * and always exposes a click-to-chat fallback link for manual sending.
 *
 * Used for fee reminders, AC-bill reminders, renewal reminders and admission
 * confirmations across the app.
 */
class WhatsAppService
{
    public function enabled(): bool
    {
        return (bool) config('services.whatsapp.enabled')
            && filled(config('services.whatsapp.api_url'))
            && filled(config('services.whatsapp.token'));
    }

    /**
     * A wa.me click-to-chat link (no API needed).
     */
    public function link(?string $mobile, string $message): ?string
    {
        return hostelease_whatsapp_link($mobile, $message);
    }

    /**
     * Attempt to send via the Cloud API. Returns true on success.
     * Falls back to logging when the integration is disabled.
     */
    public function send(?string $mobile, string $message): bool
    {
        $phone = hostelease_phone($mobile);

        if (! $phone) {
            return false;
        }

        if (! $this->enabled()) {
            Log::info('WhatsApp disabled — would send', ['to' => $phone, 'message' => $message]);

            return false;
        }

        try {
            $response = Http::withToken(config('services.whatsapp.token'))
                ->asJson()
                ->post(rtrim(config('services.whatsapp.api_url'), '/').'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => ltrim($phone, '+'),
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}

