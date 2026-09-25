@extends('public.layout')

@php
    $email = config('legal.contact_email');
    $badge = match ($deletion->status) {
        \App\Models\DataDeletionRequest::STATUS_COMPLETED, \App\Models\DataDeletionRequest::STATUS_NO_DATA => 'bg-green-100 text-green-800',
        \App\Models\DataDeletionRequest::STATUS_FAILED => 'bg-red-100 text-red-800',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp

@section('title', 'Data Deletion Status')

@section('content')
    <h1>Data deletion request status</h1>

    <table>
        <tbody>
            <tr><th scope="row">Confirmation code</th><td><code>{{ $deletion->confirmation_code }}</code></td></tr>
            <tr><th scope="row">Received</th><td>{{ $deletion->created_at->toDayDateTimeString() }} UTC</td></tr>
            <tr>
                <th scope="row">Status</th>
                <td><span class="inline-block rounded-full px-2.5 py-0.5 text-sm font-medium {{ $badge }}">{{ $deletion->statusLabel() }}</span></td>
            </tr>
            @if ($deletion->completed_at)
                <tr><th scope="row">Completed</th><td>{{ $deletion->completed_at->toDayDateTimeString() }} UTC</td></tr>
            @endif
        </tbody>
    </table>

    @if ($deletion->isFinished())
        <p>
            Your request has been processed. Any conversations, messages and technical records linked to the Facebook
            account that made this request have been removed from our systems, and our access through that login has ended.
        </p>
        <p>
            If you also messaged one of our Pages or Instagram accounts and want those messages deleted, please follow
            <a href="{{ route('public.data-deletion') }}#options">Option 1 on the Data Deletion Instructions page</a>,
            because Facebook uses different IDs for Facebook Login and for Page conversations.
        </p>
    @elseif ($deletion->status === \App\Models\DataDeletionRequest::STATUS_FAILED)
        <p>Automatic processing did not complete. Our team has been notified and will finish the deletion manually within 30 days.</p>
    @else
        <p>Your request is being processed and will be completed within 30 days.</p>
    @endif

    <p>Questions? Email <a href="mailto:{{ $email }}">{{ $email }}</a> and quote your confirmation code.</p>
@endsection
