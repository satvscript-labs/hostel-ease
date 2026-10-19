{{ __('Hi :name,', ['name' => $name]) }}

{{ __('Your :app sign-in code is:', ['app' => config('app.name', 'HostelEase')]) }}

{{ $code }}

{{ __('This code expires in :m minutes.', ['m' => $minutes]) }}

{{ __('Never share this code. If you did not try to sign in, someone may know your password — change it from your Security page.') }}
