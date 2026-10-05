{{-- Team invite + one-tap email confirmation. Table layout + inline styles for mail clients. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>{{ __('Confirm your email') }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f2f9; font-family:'Plus Jakarta Sans', 'Segoe UI', Helvetica, Arial, sans-serif; color:#0f172a;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ __(':who added you to the team. Confirm your email to finish.', ['who' => $invitedBy]) }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f2f9; padding:40px 16px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 12px 32px rgba(30,27,75,0.10);">
                <tr><td style="background:#1e1b4b; background-image:linear-gradient(135deg,#1e1b4b 0%,#312e81 100%); padding:28px 32px;">
                    <span style="font-size:19px; font-weight:800; letter-spacing:-0.4px; color:#ffffff;">{{ config('app.name', 'HostelEase') }}</span>
                </td></tr>
                <tr><td style="padding:36px 32px 8px;">
                    <p style="margin:0 0 6px; font-size:15px; color:#475569;">{{ __('Hi :name,', ['name' => $member->name]) }}</p>
                    <h1 style="margin:0 0 14px; font-size:24px; line-height:1.25; font-weight:800; letter-spacing:-0.6px; color:#0f172a;">{{ __('You have been added to the team') }}</h1>
                    <p style="margin:0; font-size:15px; line-height:1.6; color:#475569;">{{ __(':who added you as :role at :branches.', ['who' => $invitedBy, 'role' => $roleLabel, 'branches' => $branches]) }}</p>
                </td></tr>
                <tr><td style="padding:24px 32px 8px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                        <td align="center" style="background:#f5f5ff; border:1px solid #e0e0fb; border-radius:12px; padding:16px 12px;">
                            <span style="font-size:13px; color:#64748b;">{{ __('Your login is your mobile number') }}</span><br>
                            <span style="font-size:20px; font-weight:800; letter-spacing:0.5px; color:#312e81;">{{ hostelease_phone($member->mobile) }}</span>
                        </td>
                    </tr></table>
                    <p style="margin:12px 0 0; font-size:13px; line-height:1.6; color:#64748b;">{{ __('Ask :who for your password — we never send it by email.', ['who' => $invitedBy]) }}</p>
                </td></tr>
                <tr><td align="center" style="padding:24px 32px 8px;">
                    <a href="{{ $link }}" style="display:inline-block; background:#4f46e5; color:#ffffff; text-decoration:none; font-weight:700; font-size:15px; padding:14px 32px; border-radius:999px;">{{ __('Confirm my email') }}</a>
                </td></tr>
                <tr><td style="padding:16px 32px 32px;">
                    <p style="margin:0 0 6px; font-size:13px; line-height:1.6; color:#64748b;">{{ __('This link works for :d days.', ['d' => $days]) }}</p>
                    <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">{{ __('If this is not for you, ignore this email.') }}</p>
                </td></tr>
            </table>
            <p style="margin:20px 0 0; font-size:12px; color:#94a3b8;">{{ config('hostelease.company.legal_name', 'SatvScript') }}</p>
        </td></tr>
    </table>
</body>
</html>
