{{-- Signup verification code. Table layout + inline styles on purpose: this is what
     renders the same in Gmail, Outlook and phone mail apps. No external images, so
     nothing is blocked and the code is visible immediately. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <title>{{ __('Your verification code') }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f2f9; font-family:'Plus Jakarta Sans', 'Segoe UI', Helvetica, Arial, sans-serif; color:#0f172a;">
    {{-- Preheader: the line most mail apps show beside the subject. --}}
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ __('Enter this code to finish setting up your hostel. It expires in :m minutes.', ['m' => $minutes]) }}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f2f9; padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 12px 32px rgba(30,27,75,0.10);">
                    {{-- The brand band: the same indigo night as the sign-up panel. --}}
                    <tr>
                        <td style="background:#1e1b4b; background-image:linear-gradient(135deg,#1e1b4b 0%,#312e81 100%); padding:28px 32px;">
                            <span style="font-size:19px; font-weight:800; letter-spacing:-0.4px; color:#ffffff;">{{ config('app.name', 'HostelEase') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px 8px;">
                            <p style="margin:0 0 6px; font-size:15px; color:#475569;">{{ __('Hi :name,', ['name' => $name]) }}</p>
                            <h1 style="margin:0 0 14px; font-size:24px; line-height:1.25; font-weight:800; letter-spacing:-0.6px; color:#0f172a;">{{ __('Here is your verification code') }}</h1>
                            <p style="margin:0; font-size:15px; line-height:1.6; color:#475569;">{{ __('Enter it on the sign-up page to finish setting up your hostel.') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="background:#f5f5ff; border:1px solid #e0e0fb; border-radius:12px; padding:22px 12px;">
                                        <span style="font-size:36px; font-weight:800; letter-spacing:12px; color:#312e81; font-variant-numeric:tabular-nums; padding-left:12px;">{{ $code }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 32px;">
                            <p style="margin:0 0 6px; font-size:13px; line-height:1.6; color:#64748b;">{{ __('This code expires in :m minutes.', ['m' => $minutes]) }}</p>
                            <p style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">{{ __('If you did not try to sign up for :app, you can ignore this email — no account has been created.', ['app' => config('app.name', 'HostelEase')]) }}</p>
                        </td>
                    </tr>
                </table>
                <p style="margin:20px 0 0; font-size:12px; color:#94a3b8;">{{ config('hostelease.company.legal_name', 'SatvScript') }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
