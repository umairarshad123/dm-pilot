@extends('public.layout')

@php
    $operator = config('legal.operator_name');
    $product = config('legal.product_name');
    $email = config('legal.contact_email');
@endphp

@section('title', 'Data Deletion Instructions')

@section('content')
    <h1>Data Deletion Instructions</h1>
    <p>
        You can ask {{ $operator }} to delete the data {{ $product }} holds about you at any time. Deletion is free and
        we complete it within <strong>30 days</strong> (usually much sooner).
    </p>

    <h2 id="what">What gets deleted</h2>
    <ul>
        <li>Your conversation(s) with the connected Facebook Page or Instagram account, including all message text and attachment links;</li>
        <li>your contact record (name, username, profile picture link, and any email, phone number, tags or notes);</li>
        <li>raw technical copies of your messages received from Meta (webhook events).</li>
    </ul>
    <p>We keep only a minimal audit record that a deletion request was received and completed (date, confirmation code and status, without message content). Messages remain visible in your own Messenger/Instagram inbox and in the Page's inbox on Meta, which are controlled by Meta and the Page, not by us.</p>

    <h2 id="options">How to request deletion</h2>

    <h3>Option 1 &mdash; If you messaged one of our Pages or Instagram accounts</h3>
    <ol>
        <li>Open the same conversation in Messenger or Instagram.</li>
        <li>Send the message: <code>Delete my data</code>.</li>
        <li>A member of our team will confirm, delete your conversation and contact record, and reply to confirm once done.</li>
    </ol>
    <p>
        Or email <a href="mailto:{{ $email }}?subject=Data%20deletion%20request">{{ $email }}</a> with the subject
        <em>"Data deletion request"</em>, the name of the Page or Instagram account you messaged, your Facebook/Instagram
        display name or username, and the approximate date of your last message, so we can find your conversation.
        We may ask you to confirm the request from the same account before deleting.
    </p>

    <h3>Option 2 &mdash; If you connected a Page with Facebook Login</h3>
    <ol>
        <li>Go to your Facebook <strong>Settings &amp; privacy &rarr; Settings</strong>.</li>
        <li>Open <strong>Apps and websites</strong> (under "Your activity" / "Security").</li>
        <li>Find <strong>{{ $product }}</strong> and click <strong>Remove</strong>.</li>
        <li>Then click <strong>View removal request</strong> / <strong>Send request</strong> to ask us to delete your data.</li>
    </ol>
    <p>
        Facebook then sends us a signed deletion request. We delete matching data automatically and give you a
        <strong>confirmation code</strong> and a status page link where you can check progress at any time.
        Removing the app also revokes our access to your Pages immediately.
    </p>

    <h2 id="note">Good to know</h2>
    <p>
        Facebook assigns a different, app-specific ID to you for Facebook Login than the ID it uses in a Page's
        Messenger inbox, and there is no way for us to link the two. If you want the <em>messages</em> you sent to one
        of our Pages deleted, please use Option 1 so we can locate them with certainty.
    </p>

    <h2 id="contact">Questions</h2>
    <p>Email <a href="mailto:{{ $email }}">{{ $email }}</a>. See also our <a href="{{ route('public.privacy') }}">Privacy Policy</a>.</p>
@endsection
