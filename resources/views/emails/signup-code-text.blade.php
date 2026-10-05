{{ __('Hi :name,', ['name' => $name]) }}

{{ __('Your :app verification code is:', ['app' => config('app.name', 'HostelEase')]) }}

{{ $code }}

{{ __('Enter it on the sign-up page to finish setting up your hostel. This code expires in :m minutes.', ['m' => $minutes]) }}

{{ __('If you did not try to sign up for :app, you can ignore this email — no account has been created.', ['app' => config('app.name', 'HostelEase')]) }}
