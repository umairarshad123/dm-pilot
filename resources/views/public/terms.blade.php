@extends('public.layout')

@php
    $operator = config('legal.operator_name');
    $product = config('legal.product_name');
    $email = config('legal.contact_email');
    $effective = \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->format('F j, Y');
@endphp

@section('title', 'Terms of Service')

@section('content')
    <h1>Terms of Service</h1>
    <p class="text-sm text-slate-500">Effective date: {{ $effective }}</p>

    <p>
        These Terms govern your use of <strong>{{ $product }}</strong> (the "Service"), operated by
        <strong>{{ $operator }}</strong>. The Service answers direct messages sent to Facebook Pages and Instagram
        professional accounts connected to it, using automated (AI-assisted) replies and human team members.
        By messaging a connected Page or account, or by using our dashboard, you agree to these Terms and to our
        <a href="{{ route('public.privacy') }}">Privacy Policy</a>.
    </p>

    <h2 id="service">1. The Service</h2>
    <ul>
        <li>Replies may be generated automatically by an AI assistant. Automated replies are provided for convenience, can be incomplete or inaccurate, and are not professional, legal, medical or financial advice.</li>
        <li>Prices, availability, bookings and policies are only binding when confirmed by a member of our team.</li>
        <li>You can ask to speak with a person at any time; a team member can take over the conversation and the assistant will pause.</li>
        <li>We reply only to conversations you start and only within the time windows allowed by Meta's messaging policies.</li>
    </ul>

    <h2 id="acceptable-use">2. Acceptable use</h2>
    <p>You agree not to use the Service to:</p>
    <ul>
        <li>send unlawful, harassing, hateful, sexually explicit or violent content, or content that infringes others' rights;</li>
        <li>attempt to extract confidential information, manipulate the assistant into producing harmful content, or disrupt the Service;</li>
        <li>send spam, malware, or automated high-volume messages;</li>
        <li>share sensitive data (such as payment card numbers, passwords or health records) in chat. Please don't send these.</li>
    </ul>
    <p>We may stop replying to, or block, conversations that violate these Terms or Meta's Community Standards.</p>

    <h2 id="business-users">3. Business users and team members</h2>
    <p>
        If you connect a Facebook Page or Instagram account, or use the dashboard, you confirm that you are authorized to
        manage that Page or account, that you will comply with the
        <a href="https://developers.facebook.com/terms/" rel="noopener" target="_blank">Meta Platform Terms</a>,
        the <a href="https://developers.facebook.com/docs/messenger-platform/policy/policy-overview" rel="noopener" target="_blank">Messenger Platform &amp; Instagram Messaging policies</a>
        and applicable law, and that you will keep your login credentials confidential. You are responsible for the business
        information and instructions you give the assistant and for replies you send manually.
    </p>

    <h2 id="meta">4. Meta platforms</h2>
    <p>
        Facebook, Messenger and Instagram are provided by Meta Platforms, Inc. and are subject to Meta's own terms. We are
        not affiliated with or endorsed by Meta. Features of the Service depend on Meta's APIs and may change or stop
        working if Meta changes them.
    </p>

    <h2 id="privacy">5. Privacy and data deletion</h2>
    <p>
        Our handling of personal information is described in the <a href="{{ route('public.privacy') }}">Privacy Policy</a>.
        You may request deletion of your data at any time as described on the
        <a href="{{ route('public.data-deletion') }}">Data Deletion Instructions</a> page.
    </p>

    <h2 id="ip">6. Intellectual property</h2>
    <p>The Service, its software and branding belong to {{ $operator }}. You keep ownership of the content you send us and grant us a limited licence to process it to provide the Service as described in the Privacy Policy.</p>

    <h2 id="disclaimer">7. Disclaimers</h2>
    <p>The Service is provided "as is" and "as available", without warranties of any kind, to the fullest extent permitted by law. We do not guarantee that replies will be uninterrupted, timely or error-free.</p>

    <h2 id="liability">8. Limitation of liability</h2>
    <p>To the fullest extent permitted by law, {{ $operator }} is not liable for indirect, incidental, special or consequential damages, or for decisions made in reliance on automated replies. Nothing in these Terms limits liability that cannot be limited by law, or your statutory consumer rights.</p>

    <h2 id="termination">9. Suspension and termination</h2>
    <p>You can stop using the Service at any time by no longer messaging the connected Page or account, or by disconnecting your Page. We may suspend or end the Service, or any conversation, at any time, for example to comply with law or Meta policies.</p>

    <h2 id="changes">10. Changes</h2>
    <p>We may update these Terms. The effective date above shows the latest version. Continuing to use the Service after a change means you accept the updated Terms.</p>

    <h2 id="law">11. Governing law</h2>
    <p>These Terms are governed by the laws of the State of Wyoming, United States, without regard to its conflict-of-law rules. {{ $operator }} is a limited liability company organized in Wyoming.</p>

    <h2 id="contact">12. Contact</h2>
    <p>Questions about these Terms: <a href="mailto:{{ $email }}">{{ $email }}</a>.</p>
@endsection
