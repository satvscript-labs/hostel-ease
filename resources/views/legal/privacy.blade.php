<x-legal-shell :title="__('Privacy Policy')" updated="{{ date('d M Y') }}">
    <div class="lg-toc">
        <a href="#collect">1. {{ __('What We Collect') }}</a>
        <a href="#use">2. {{ __('How We Use It') }}</a>
        <a href="#legal">3. {{ __('Legal Basis') }}</a>
        <a href="#share">4. {{ __('Sharing') }}</a>
        <a href="#security">5. {{ __('Security') }}</a>
        <a href="#retention">6. {{ __('Retention') }}</a>
        <a href="#rights">7. {{ __('Your Rights') }}</a>
        <a href="#contact">8. {{ __('Contact') }}</a>
    </div>

    <p>{{ __('This Privacy Policy explains how HostelEase, operated by SatvScript, collects, uses, and protects personal data. We take privacy seriously — especially the sensitive identity data that hostel operations involve.') }}</p>

    <h2 id="collect">1. {{ __('Information We Collect') }}</h2>
    <h3>{{ __('From hostel owners and staff') }}</h3>
    <ul>
        <li>{{ __('Name, mobile number, and role;') }}</li>
        <li>{{ __('login credentials (stored only as a secure one-way hash — never in plain text);') }}</li>
        <li>{{ __('activity logs such as sign-in times and actions taken, for security and audit.') }}</li>
    </ul>
    <h3>{{ __('From students and residents (entered by the operator)') }}</h3>
    <ul>
        <li>{{ __('Name, mobile numbers, address, and occupation details;') }}</li>
        <li>{{ __('identity documents such as Aadhaar number and its scan, and a photograph;') }}</li>
        <li>{{ __('billing, payment, deposit, and stay records.') }}</li>
    </ul>
    <p>{{ __('When a student registers through a public link, they provide this information directly to the operator of that hostel.') }}</p>

    <h2 id="use">2. {{ __('How We Use Information') }}</h2>
    <ul>
        <li>{{ __('to provide the Service — managing rooms, residents, billing, and reports;') }}</li>
        <li>{{ __('to process subscription payments through our payment provider;') }}</li>
        <li>{{ __('to secure accounts, prevent misuse, and keep an audit trail;') }}</li>
        <li>{{ __('to provide support and to send essential service notices.') }}</li>
    </ul>
    <p>{{ __('We do not sell personal data, and we do not use student identity documents for any purpose other than providing the Service to the operator who collected them.') }}</p>

    <h2 id="legal">3. {{ __('Roles and Legal Basis') }}</h2>
    <p>{{ __('For student and staff records, the hostel operator is the data controller (or Data Fiduciary) and decides how that data is used; HostelEase acts as the processor on their behalf. Operators are responsible for having a lawful basis and appropriate consent for the data they collect, in line with the Digital Personal Data Protection Act, 2023 and other applicable laws.') }}</p>

    <h2 id="share">4. {{ __('How We Share Information') }}</h2>
    <p>{{ __('We share personal data only:') }}</p>
    <ul>
        <li>{{ __('with the payment provider, strictly to process subscription payments;') }}</li>
        <li>{{ __('with infrastructure providers that host the Service under confidentiality obligations;') }}</li>
        <li>{{ __('where required by law, court order, or to protect rights and safety.') }}</li>
    </ul>

    <h2 id="security">5. {{ __('How We Protect Data') }}</h2>
    <ul>
        <li>{{ __('Uploaded documents (Aadhaar cards, photos, agreements) are stored on private storage, outside the public web, and are served only after an authenticated, permission-checked request — they have no public link.') }}</li>
        <li>{{ __('Each hostel\'s data is isolated so one account cannot access another\'s records.') }}</li>
        <li>{{ __('Passwords are stored as secure one-way hashes; access is role-based and logged.') }}</li>
        <li>{{ __('Data is transmitted over encrypted connections (HTTPS).') }}</li>
    </ul>

    <h2 id="retention">6. {{ __('Data Retention') }}</h2>
    <p>{{ __('We retain personal data for as long as the account is active and as needed to provide the Service, and thereafter only as required for legal, tax, or audit obligations. Operators can update or delete individual records within the Service. On account closure, data may be deleted after a reasonable export window.') }}</p>

    <h2 id="rights">7. {{ __('Your Rights') }}</h2>
    <p>{{ __('Subject to applicable law, you may request access to, correction of, or deletion of your personal data, and may withdraw consent where processing relies on it. Students and residents should direct such requests to the hostel operator who holds their records; we will assist operators in fulfilling them.') }}</p>

    <h2 id="contact">8. {{ __('Cookies and Contact') }}</h2>
    <p>{{ __('We use only essential cookies needed to keep you signed in and to keep the Service secure. We may update this Policy from time to time; material changes will be notified within the Service.') }}</p>
    <p>{{ __('For privacy questions or requests, contact us at') }} <a href="mailto:{{ config('mail.from.address', 'support@hostelease.app') }}">{{ config('mail.from.address', 'support@hostelease.app') }}</a>.</p>
</x-legal-shell>
