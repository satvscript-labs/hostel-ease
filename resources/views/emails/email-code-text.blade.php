{{ __('Hi :name,', ['name' => $name]) }}

{{ __('Your :app verification code is:', ['app' => config('app.name', 'HostelEase')]) }}

{{ $code }}

{{ __('Enter it in your profile to confirm this email. This code expires in :m minutes.', ['m' => $minutes]) }}

{{ __('If you did not ask for this, ignore this email — nothing has changed on your account.') }}
