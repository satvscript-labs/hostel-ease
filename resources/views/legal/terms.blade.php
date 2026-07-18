<x-legal-shell :title="__('Terms of Service')" updated="{{ date('d M Y') }}">
    <div class="lg-toc">
        <a href="#accept">1. {{ __('Acceptance') }}</a>
        <a href="#service">2. {{ __('The Service') }}</a>
        <a href="#accounts">3. {{ __('Accounts') }}</a>
        <a href="#billing">4. {{ __('Billing') }}</a>
        <a href="#use">5. {{ __('Acceptable Use') }}</a>
        <a href="#data">6. {{ __('Your Data') }}</a>
        <a href="#ip">7. {{ __('Intellectual Property') }}</a>
        <a href="#term">8. {{ __('Termination') }}</a>
        <a href="#liability">9. {{ __('Liability') }}</a>
        <a href="#law">10. {{ __('Governing Law') }}</a>
    </div>

    <p>{{ __('These Terms of Service ("Terms") govern your access to and use of the HostelEase platform ("Service"), operated by SatvScript ("we", "us"). By creating an account or using the Service, you agree to these Terms.') }}</p>

    <h2 id="accept">1. {{ __('Acceptance of Terms') }}</h2>
    <p>{{ __('By registering for, accessing, or using the Service, you confirm that you are at least 18 years old and legally able to enter into this agreement, and that you accept these Terms on behalf of yourself and any organisation you represent.') }}</p>

    <h2 id="service">2. {{ __('Description of the Service') }}</h2>
    <p>{{ __('HostelEase is a software-as-a-service platform that helps hostel, PG, and dormitory operators manage rooms and beds, students and residents, billing and payments, staff, and related records. We may add, change, or remove features over time to improve the Service.') }}</p>

    <h2 id="accounts">3. {{ __('Accounts and Security') }}</h2>
    <ul>
        <li>{{ __('You are responsible for the accuracy of the information you provide and for all activity under your account.') }}</li>
        <li>{{ __('You must keep your login credentials confidential and notify us promptly of any unauthorised use.') }}</li>
        <li>{{ __('Account owners are responsible for the staff sub-users they create and the access they grant them.') }}</li>
    </ul>

    <h2 id="billing">4. {{ __('Subscriptions and Billing') }}</h2>
    <ul>
        <li>{{ __('New accounts include a free trial period. After the trial, continued use of a branch requires an active paid subscription.') }}</li>
        <li>{{ __('Subscriptions are billed per branch, on a monthly or yearly basis, at the prices shown at the time of purchase.') }}</li>
        <li>{{ __('Prices may change with reasonable prior notice; changes do not affect a subscription already paid for its current term.') }}</li>
        <li>{{ __('Payments are processed by our third-party payment provider; we do not store your full card details.') }}</li>
        <li>{{ __('Refund and cancellation terms are described in our Refund & Cancellation Policy.') }}</li>
    </ul>

    <h2 id="use">5. {{ __('Acceptable Use') }}</h2>
    <p>{{ __('You agree not to:') }}</p>
    <ul>
        <li>{{ __('use the Service for any unlawful purpose or in violation of any applicable law or regulation;') }}</li>
        <li>{{ __('upload data you do not have the right to store, or use another person\'s identity documents without their consent;') }}</li>
        <li>{{ __('attempt to gain unauthorised access to the Service, other accounts, or our systems;') }}</li>
        <li>{{ __('interfere with, disrupt, or place undue load on the Service;') }}</li>
        <li>{{ __('resell, sublicense, or reverse-engineer the Service without our written permission.') }}</li>
    </ul>

    <h2 id="data">6. {{ __('Your Data') }}</h2>
    <p>{{ __('You retain ownership of the data you enter into the Service ("Your Data"), including student and resident records. You grant us a limited licence to store and process Your Data solely to provide and improve the Service. Our handling of personal data is described in our Privacy Policy. You are the data controller for the personal data of your students and staff; we act as your data processor.') }}</p>

    <h2 id="ip">7. {{ __('Intellectual Property') }}</h2>
    <p>{{ __('The Service, including its software, design, and branding, is owned by us and protected by intellectual-property laws. These Terms grant you a limited, non-exclusive, non-transferable right to use the Service; they do not transfer any ownership to you.') }}</p>

    <h2 id="term">8. {{ __('Suspension and Termination') }}</h2>
    <ul>
        <li>{{ __('You may stop using the Service at any time; unpaid branches lose access after any applicable grace period.') }}</li>
        <li>{{ __('We may suspend or terminate access for a serious or repeated breach of these Terms, or where required by law.') }}</li>
        <li>{{ __('On termination, you may request an export of Your Data within a reasonable period, after which it may be deleted.') }}</li>
    </ul>

    <h2 id="liability">9. {{ __('Disclaimers and Limitation of Liability') }}</h2>
    <p>{{ __('The Service is provided "as is" without warranties of any kind. To the maximum extent permitted by law, we are not liable for indirect, incidental, or consequential damages, and our total liability for any claim is limited to the fees you paid for the Service in the twelve months before the claim.') }}</p>

    <h2 id="law">10. {{ __('Governing Law and Contact') }}</h2>
    <p>{{ __('These Terms are governed by the laws of India, and any disputes are subject to the courts of competent jurisdiction in India. We may update these Terms from time to time; material changes will be notified within the Service or by other reasonable means.') }}</p>
    <p>{{ __('Questions about these Terms? Contact us at') }} <a href="mailto:{{ config('mail.from.address', 'support@hostelease.app') }}">{{ config('mail.from.address', 'support@hostelease.app') }}</a>.</p>
</x-legal-shell>
