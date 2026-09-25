@extends('public.layout')

@php
    $operator = config('legal.operator_name');
    $product = config('legal.product_name');
    $email = config('legal.contact_email');
    $address = config('legal.operator_address');
    $retention = config('legal.retention');
    $effective = \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y');
@endphp

@section('title', 'Privacy Policy')

@section('content')
    <h1>Privacy Policy</h1>
    <p class="text-sm text-slate-500">Effective date: {{ $effective }} &middot; Last updated: {{ $effective }}</p>

    <p>
        This Privacy Policy explains how <strong>{{ $operator }}</strong> ("we", "us") collects, uses, shares and deletes
        information through <strong>{{ $product }}</strong> (the "Service"), a messaging assistant that answers direct
        messages sent to Facebook Pages (Messenger) and Instagram professional accounts that we own or manage on behalf of
        our business and our clients. It applies to people who send direct messages to those Pages or accounts
        ("customers") and to team members who use our admin dashboard.
    </p>

    <h2 id="summary">1. Summary</h2>
    <ul>
        <li>We only process messages that <strong>you choose to send</strong> to a connected Facebook Page or Instagram account.</li>
        <li>We use them to reply to you (automatically and by humans) and to follow up on your enquiry.</li>
        <li>Message text may be processed by our AI providers (OpenAI and/or Anthropic) solely to draft replies.</li>
        <li>We never sell your data and never use it for advertising.</li>
        <li>You can ask us to delete your data at any time: see <a href="{{ route('public.data-deletion') }}">Data Deletion Instructions</a>.</li>
    </ul>

    <h2 id="data">2. Information we receive</h2>
    <p>When you message a connected Page or Instagram account, Meta Platforms, Inc. sends us the following through the Messenger Platform and the Instagram Messaging API:</p>
    <table>
        <thead><tr><th>Data</th><th>Details</th></tr></thead>
        <tbody>
            <tr><td>Scoped user ID</td><td>The Page-scoped ID (PSID) for Messenger or the Instagram-scoped ID (IGSID) for Instagram. These IDs are specific to the Page/account and are not your Facebook or Instagram account ID.</td></tr>
            <tr><td>Message content</td><td>The text of your messages, quick-reply and button selections (postbacks), and reactions or read/delivery signals where provided.</td></tr>
            <tr><td>Attachments</td><td>Links (URLs) to images, audio, video, files or shared posts you send. We store the link Meta provides; we do not intentionally download or keep copies of the media itself.</td></tr>
            <tr><td>Public profile</td><td>Where Meta makes it available: your name, profile picture URL and, on Instagram, your username.</td></tr>
            <tr><td>Timestamps &amp; message IDs</td><td>When each message was sent or received, and Meta's message identifiers (used to avoid duplicate replies).</td></tr>
            <tr><td>Information you share</td><td>Anything you decide to write in a message, such as your email address, phone number, or details of your enquiry. We may save your email or phone number to your contact record so we can follow up.</td></tr>
        </tbody>
    </table>
    <p>
        From the business owners and team members who use the Service we receive their Facebook Login authorization,
        the list of Pages/Instagram accounts they manage, and Page access tokens needed to send replies, plus their
        admin account email and name.
    </p>
    <p>We do <strong>not</strong> collect your password, payment card data, contacts list, location, or any data from people who have not messaged a connected Page or account.</p>

    <h2 id="use">3. How we use information</h2>
    <ul>
        <li><strong>Replying to your messages.</strong> An automated assistant generates short replies to messages you initiated, within Meta's standard 24-hour messaging window.</li>
        <li><strong>Human support.</strong> Our team can read the conversation in our dashboard, take over from the assistant and reply personally. When a person replies, the assistant pauses in that conversation.</li>
        <li><strong>Lead and customer management.</strong> Organizing conversations (status, tags, notes, contact details you shared) so the business can follow up on your enquiry.</li>
        <li><strong>Operating and securing the Service.</strong> Preventing duplicate or unwanted replies, debugging delivery errors, and protecting against abuse.</li>
    </ul>
    <p>
        We do not use your messages to send you marketing outside the conversation you started, we do not send
        promotional messages outside Meta's permitted messaging windows, and we do not use Meta Platform data to build
        advertising or user profiles, or to train AI models.
    </p>

    <h2 id="legal-bases">4. Legal bases (EEA/UK users)</h2>
    <p>
        We process your information because it is necessary to respond to the request you made by messaging us
        (steps prior to or performance of a contract), and for our legitimate interests in providing fast customer
        service and managing enquiries. Where required by law, we rely on your consent, which you may withdraw at any time.
    </p>

    <h2 id="ai">5. AI processing and sub-processors</h2>
    <p>To generate automatic replies, the text of recent messages in your conversation (and the business information we provide) is sent to one of the following AI providers, acting as our service providers / sub-processors:</p>
    <ul>
        @foreach (config('legal.ai_subprocessors') as $sub)
            <li><strong>{{ $sub['name'] }}</strong> &mdash; {{ $sub['purpose'] }} (<a href="{{ $sub['url'] }}" rel="noopener" target="_blank">privacy policy</a>).</li>
        @endforeach
    </ul>
    <p>
        These providers process the data via their business APIs under terms that do not permit them to use API data to
        train their models by default. We send only what is needed to write the reply: we do not send your access
        tokens or Meta account identifiers to AI providers.
    </p>
    <p>Other service providers we use: our hosting and database provider (servers located where our infrastructure is hosted), and Meta Platforms itself, which delivers the messages.</p>

    <h2 id="sharing">6. Sharing</h2>
    <p>
        We share information only with the sub-processors listed above, with the business whose Page or account you
        messaged (if we operate the Service on its behalf), when required by law, or to protect rights and safety.
        We do <strong>not</strong> sell, rent or trade personal information, and we do not share Meta Platform data with
        data brokers or advertising networks.
    </p>

    <h2 id="retention">7. Retention</h2>
    <table>
        <thead><tr><th>Data</th><th>Kept for</th></tr></thead>
        <tbody>
            <tr><td>Conversations, messages and contact details</td><td>Up to {{ $retention['conversations_months'] }} months after the last message in the conversation, unless you ask us to delete them sooner or a longer period is required by law.</td></tr>
            <tr><td>Raw webhook events received from Meta (technical logs)</td><td>{{ $retention['webhook_events_days'] }} days, then deleted automatically.</td></tr>
            <tr><td>Application error logs</td><td>Up to {{ $retention['logs_days'] }} days.</td></tr>
            <tr><td>Page access tokens</td><td>Until the Page is disconnected, the token is revoked, or the app is removed from the account.</td></tr>
            <tr><td>Deletion request records</td><td>Kept as an audit record (confirmation code, date, status; no message content).</td></tr>
        </tbody>
    </table>

    <h2 id="security">8. Security</h2>
    <ul>
        <li>All traffic to and from the Service uses HTTPS (TLS).</li>
        <li>Meta access tokens are encrypted at rest with AES-256 application encryption and are never shown in full in the dashboard or logs.</li>
        <li>Incoming webhooks are verified with Meta's HMAC-SHA256 signature before they are processed.</li>
        <li>The dashboard is restricted to authorized team members with individual logins.</li>
    </ul>
    <p>No system is perfectly secure; if we become aware of a breach affecting your data we will notify you and the authorities where required.</p>

    <h2 id="rights">9. Your rights and choices</h2>
    <p>Depending on where you live (for example under the GDPR, UK GDPR or US state privacy laws) you may have the right to:</p>
    <ul>
        <li>access the personal information we hold about you and receive a copy;</li>
        <li>correct inaccurate information;</li>
        <li>delete your information (see <a href="{{ route('public.data-deletion') }}">Data Deletion Instructions</a>);</li>
        <li>object to or restrict processing, including automated replies &mdash; just write "talk to a human" in the chat or email us;</li>
        <li>lodge a complaint with your local data protection authority.</li>
    </ul>
    <p>You can stop all processing at any time by no longer messaging the Page or account and asking us to delete your conversation. We will respond within 30 days.</p>

    <h2 id="deletion">10. How to delete your data</h2>
    <p>
        Send the message <strong>"delete my data"</strong> in the same Messenger or Instagram conversation, or email
        <a href="mailto:{{ $email }}">{{ $email }}</a>. Full steps, including how removing the app from your Facebook
        settings works, are on our <a href="{{ route('public.data-deletion') }}">Data Deletion Instructions</a> page.
    </p>

    <h2 id="children">11. Children</h2>
    <p>The Service is not directed to children under 13 (or the minimum age in your country). We do not knowingly collect their data; if you believe a child has messaged us, contact us and we will delete it.</p>

    <h2 id="transfers">12. International transfers</h2>
    <p>Our providers may process data in the United States and other countries. Where required, transfers rely on appropriate safeguards such as the European Commission's Standard Contractual Clauses.</p>

    <h2 id="changes">13. Changes to this policy</h2>
    <p>We may update this policy. We will change the "Last updated" date above and, for material changes, give notice on this page.</p>

    <h2 id="contact">14. Contact</h2>
    <p>
        {{ $operator }}@if ($address)<br>{{ $address }}@endif<br>
        Email: <a href="mailto:{{ $email }}">{{ $email }}</a>
    </p>
@endsection
