@extends('public.layout')

@php
    $operator = config('legal.operator_name');
    $product = config('legal.product_name');
    $email = config('legal.contact_email');
@endphp

@section('title', 'About')

@section('content')
    <h1>{{ $product }}</h1>
    <p class="text-lg text-slate-600">An AI-assisted inbox for Facebook Messenger and Instagram direct messages, operated by {{ $operator }}.</p>

    <h2 id="what">What it does</h2>
    <ul>
        <li><strong>Instant replies to customer DMs.</strong> When someone messages a connected Facebook Page or Instagram professional account, the assistant answers common questions (services, hours, pricing ranges, next steps) within seconds, using business information the Page owner provides.</li>
        <li><strong>Human handover.</strong> Every conversation appears in a private team dashboard. A team member can reply personally at any time; the assistant then pauses in that conversation so the customer talks to a person.</li>
        <li><strong>Lead management.</strong> Conversations are organized by status and tags, and contact details the customer chooses to share are saved so the team can follow up.</li>
    </ul>

    <h2 id="how">How it works</h2>
    <ol>
        <li>A Page admin connects their Facebook Page (and its linked Instagram professional account) with Facebook Login for Business.</li>
        <li>Meta sends new direct messages to our secure webhook.</li>
        <li>The assistant drafts a short reply with an AI model (OpenAI or Anthropic) and sends it through the Messenger Platform / Instagram Messaging API &mdash; only in reply to a message the customer sent, and only within Meta's 24-hour messaging window.</li>
        <li>Team members review conversations and reply personally from the dashboard when needed.</li>
    </ol>

    <h2 id="permissions">Meta permissions we use</h2>
    <table>
        <thead><tr><th>Permission</th><th>Why</th></tr></thead>
        <tbody>
            <tr><td><code>pages_show_list</code></td><td>List the Pages the admin manages so they can pick which one to connect.</td></tr>
            <tr><td><code>pages_manage_metadata</code></td><td>Subscribe the connected Page to message webhooks.</td></tr>
            <tr><td><code>pages_read_engagement</code></td><td>Read the Page's name and the sender's name/profile picture to label conversations.</td></tr>
            <tr><td><code>pages_messaging</code></td><td>Receive and reply to Messenger conversations started by customers.</td></tr>
            <tr><td><code>instagram_basic</code></td><td>Identify the Instagram professional account linked to the Page (ID, username).</td></tr>
            <tr><td><code>instagram_manage_messages</code></td><td>Receive and reply to Instagram direct messages started by customers.</td></tr>
        </tbody>
    </table>

    <h2 id="try">Try it</h2>
    <p>Send a direct message to a Facebook Page or Instagram account that uses {{ $product }}: you'll receive an automatic reply, and a team member can join the conversation at any time.</p>

    <h2 id="contact">Contact</h2>
    <p>
        {{ $operator }} &middot; <a href="mailto:{{ $email }}">{{ $email }}</a><br>
        <a href="{{ route('public.privacy') }}">Privacy Policy</a> &middot;
        <a href="{{ route('public.terms') }}">Terms of Service</a> &middot;
        <a href="{{ route('public.data-deletion') }}">Data Deletion Instructions</a>
    </p>
@endsection
