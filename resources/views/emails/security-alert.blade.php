{{-- Sign-in security changed (S7.1). Same shell as email-code. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>{{ $head ?? config('app.name') }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f2f9; font-family:'Plus Jakarta Sans', 'Segoe UI', Helvetica, Arial, sans-serif; color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f2f9; padding:40px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 12px 32px rgba(30,27,75,0.10);">
                <tr><td style="background:#1e1b4b; background-image:linear-gradient(135deg,#1e1b4b 0%,#312e81 100%); padding:28px 32px;">
                    <span style="font-size:19px; font-weight:800; letter-spacing:-0.4px; color:#ffffff;">{{ config('app.name', 'HostelEase') }}</span>
                </td></tr>
                @php
    $head = match ($kind) {
        'enabled' => __('Two-factor sign-in is on'),
        'disabled' => __('Two-factor sign-in was turned off'),
        'reset' => __('Your two-factor sign-in was reset'),
        default => __('A recovery code was used to sign in'),
    };
    $line = match ($kind) {
        'enabled' => __('From now on you will enter a code after your password when you sign in on a new device.'),
        'disabled' => __('Signing in now needs only your mobile and password.'),
        'reset' => __('An administrator turned off two-factor sign-in on your account, usually because you lost your phone. You can turn it on again from your Security page.'),
        default => __('You have fewer recovery codes left. You can make a new set from your Security page.'),
    };
@endphp
                <tr><td style="padding:36px 32px 8px;">
                    <p style="margin:0 0 6px; font-size:15px; color:#475569;">{{ __('Hi :name,', ['name' => $name]) }}</p>
                    <h1 style="margin:0 0 14px; font-size:24px; line-height:1.25; font-weight:800; color:#0f172a;">{{ $head }}</h1>
                    <p style="margin:0; font-size:15px; line-height:1.6; color:#475569;">{{ $line }}</p>
                </td></tr>
                <tr><td style="padding:16px 32px 32px;">
                    <p style="margin:0 0 6px; font-size:13px; line-height:1.6; color:#64748b;">{{ $when }}@if($ip) · {{ __('IP address') }} {{ $ip }}@endif</p>
                    <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">{{ __('Was this not you? Change your password at once, then contact HostelEase support.') }}</p>
                </td></tr>
            </table>
            <p style="margin:20px 0 0; font-size:12px; color:#94a3b8;">{{ config('hostelease.company.legal_name', 'SatvScript') }}</p>
        </td></tr>
    </table>
</body>
</html>
