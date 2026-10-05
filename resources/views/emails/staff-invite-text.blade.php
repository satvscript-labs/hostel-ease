{{ __('Hi :name,', ['name' => $member->name]) }}

{{ __(':who added you as :role at :branches.', ['who' => $invitedBy, 'role' => $roleLabel, 'branches' => $branches]) }}

{{ __('Your login is your mobile number:') }} {{ hostelease_phone($member->mobile) }}
{{ __('Ask :who for your password — we never send it by email.', ['who' => $invitedBy]) }}

{{ __('Confirm your email:') }} {{ $link }}
{{ __('This link works for :d days.', ['d' => $days]) }}
