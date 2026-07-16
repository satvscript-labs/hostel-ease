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

if (! function_exists('hostelease_amount_words')) {
    /**
     * Spell an amount out in Indian English ("Rupees One Lakh Fifty Thousand
     * Twenty-One and Fifty Paise Only") — the legal-ish line printed on a
     * receipt so the figure can't be altered after the fact.
     *
     * Returns null when the intl extension isn't loaded rather than throwing:
     * a missing extension on a host must not take the receipt PDF down with
     * it. Callers omit the line when this is null.
     */
    function hostelease_amount_words(int|float|null $amount): ?string
    {
        if (! class_exists(\NumberFormatter::class)) {
            return null;
        }

        $amount = round((float) ($amount ?? 0), 2);
        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        $speller = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);

        $words = 'rupees '.$speller->format($rupees);
        if ($paise > 0) {
            $words .= ' and '.$speller->format($paise).' paise';
        }

        return Str::title($words).' Only';
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

if (! function_exists('hostelease_sharing_label')) {
    /**
     * Human label for a room-sharing count. The first four have friendly
     * names; beyond that we fall back to "{n} Sharing" so this scales
     * indefinitely as a hostel raises its own ceiling past Quad.
     */
    function hostelease_sharing_label(int $n): string
    {
        return match ($n) {
            1 => 'Single',
            2 => 'Double',
            3 => 'Triple',
            4 => 'Quad',
            default => "{$n} Sharing",
        };
    }
}

if (! function_exists('hostelease_max_room_sharing')) {
    /**
     * The active hostel's own room-sharing ceiling (see
     * Hostel::maxRoomSharing(), set via the Layout Builder's "Room Settings"
     * panel). Falls back to the system default outside a tenant context.
     */
    function hostelease_max_room_sharing(): int
    {
        $hostelId = \App\Support\Tenant::id();

        $hostel = $hostelId ? \App\Models\Hostel::find($hostelId) : null;

        return $hostel?->maxRoomSharing() ?? config('hostelease.default_max_room_sharing', 7);
    }
}

if (! function_exists('hostelease_sharing_labels')) {
    /**
     * Ordered labels 1..max — the single source every sharing-preference
     * picker (student profile, fee-plan gate) reads from, so raising a
     * hostel's ceiling in Room Settings is the only thing that ever needs
     * to change for every picker to grow with it.
     */
    function hostelease_sharing_labels(?int $max = null): array
    {
        $max ??= hostelease_max_room_sharing();

        return collect(range(1, max(1, $max)))
            ->map(fn ($n) => hostelease_sharing_label($n))
            ->all();
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
