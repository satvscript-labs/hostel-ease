<?php

use Illuminate\Support\Str;

if (! function_exists('hostelease_phone')) {
    /**
     * Normalise an Indian mobile number to +91XXXXXXXXXX form.
     */
    function hostelease_phone(?string $mobile): ?string
    {
        if (blank($mobile)) {
            return null;
        }

        $code = config('hostelease.country_code', '+91');
        $digits = preg_replace('/\D+/', '', $mobile);

        // Strip a leading country code if already present.
        $bare = ltrim($code, '+');
        if (Str::startsWith($digits, $bare) && strlen($digits) > 10) {
            $digits = substr($digits, strlen($bare));
        }

        $digits = substr($digits, -10);

        return $code.$digits;
    }
}

if (! function_exists('hostelease_whatsapp_link')) {
    /**
     * Build a wa.me click-to-chat link for a mobile number.
     */
    function hostelease_whatsapp_link(?string $mobile, ?string $text = null): ?string
    {
        $phone = hostelease_phone($mobile);

        if (! $phone) {
            return null;
        }

        $url = 'https://wa.me/'.ltrim($phone, '+');

        if ($text) {
            $url .= '?text='.rawurlencode($text);
        }

        return $url;
    }
}

if (! function_exists('hostelease_money')) {
    /**
     * Format a value as Indian Rupees.
     */
    function hostelease_money(int|float|null $amount): string
    {
        return '₹'.number_format((float) ($amount ?? 0), 2);
    }
}

if (! function_exists('normalize_phone')) {
    /**
     * Normalize an Indian phone number for storage: ensures +91 prefix.
     * Input: "9876543210", "09876543210", "919876543210", "+919876543210", "98765-43210"
     * Output: "+919876543210"
     * Null/empty input returns empty string.
     */
    function normalize_phone(?string $mobile): string
    {
        if (blank($mobile)) {
            return '';
        }

        // Remove all non-digit characters.
        $digits = preg_replace('/\D+/', '', $mobile);

        // Strip leading 91 or 0.
        $digits = ltrim($digits, '0');
        if (Str::startsWith($digits, '91') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }

        // Take last 10 digits (handles edge cases).
        $digits = substr($digits, -10);

        // Validate exactly 10 digits.
        if (strlen($digits) !== 10 || ! ctype_digit($digits)) {
            throw new \InvalidArgumentException("Invalid phone number: must be 10 digits after normalization.");
        }

        return '+91' . $digits;
    }
}

if (! function_exists('hostelease_receipt_number')) {
    /**
     * Generate a unique receipt number for a hostel.
     */
    function hostelease_receipt_number(int $hostelId): string
    {
        return sprintf('RCPT-%d-%s', $hostelId, strtoupper(Str::random(8)));
    }
}
