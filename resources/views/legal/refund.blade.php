<x-legal-shell :title="__('Refund & Cancellation Policy')" updated="{{ date('d M Y') }}">
    <p>{{ __('This policy explains how subscription charges, cancellations, and refunds work for the HostelEase platform, operated by SatvScript.') }}</p>

    <h2>1. {{ __('Free Trial') }}</h2>
    <p>{{ __('New accounts start with a free trial and require no payment or card details up front. You can evaluate the Service during the trial with no obligation to continue.') }}</p>

    <h2>2. {{ __('Subscription Charges') }}</h2>
    <ul>
        <li>{{ __('After the trial, continued use of a branch requires an active subscription, billed per branch on a monthly or yearly basis.') }}</li>
        <li>{{ __('Charges are shown clearly before you pay, and payment is taken through our third-party payment provider.') }}</li>
        <li>{{ __('A subscription grants access for the term you paid for (one month or one year from the renewal date).') }}</li>
    </ul>

    <h2>3. {{ __('Cancellation') }}</h2>
    <ul>
        <li>{{ __('You may choose not to renew a branch at any time; access continues until the end of the term already paid for.') }}</li>
        <li>{{ __('Cancelling stops future renewals — it does not retroactively refund the current term unless stated below.') }}</li>
    </ul>

    <h2>4. {{ __('Refunds') }}</h2>
    <ul>
        <li>{{ __('Because a free trial lets you evaluate the Service before paying, subscription fees are generally non-refundable once a term has begun.') }}</li>
        <li>{{ __('If you were charged in error, or a technical fault prevented you from using a branch you paid for, contact us within 7 days and we will review a pro-rata refund or credit in good faith.') }}</li>
        <li>{{ __('Approved refunds are returned to the original payment method through our payment provider; the time to reflect depends on your bank.') }}</li>
    </ul>

    <h2>5. {{ __('Price Changes') }}</h2>
    <p>{{ __('We may change subscription prices with reasonable prior notice. Changes never affect a term already paid for — they apply only from your next renewal.') }}</p>

    <h2>6. {{ __('Contact') }}</h2>
    <p>{{ __('For any billing question, cancellation, or refund request, contact us at') }} <a href="mailto:{{ config('mail.from.address', 'support@hostelease.app') }}">{{ config('mail.from.address', 'support@hostelease.app') }}</a> {{ __('with your registered mobile number and branch details.') }}</p>
</x-legal-shell>
