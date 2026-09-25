<?php

return [
    // Fetch name / username / profile picture from Meta when a new contact (conversation) is created.
    'fetch_profiles' => env('CONTACTS_FETCH_PROFILES', true),

    // The fetch is a background job. On the "sync" queue it would run inline in the webhook/reply
    // pipeline (extra Graph latency before the bot answers), so it is skipped there unless enabled.
    'fetch_profiles_on_sync_queue' => env('CONTACTS_FETCH_PROFILES_ON_SYNC_QUEUE', false),

    // Re-fetch profiles older than this (hours) when ContactService::refreshProfile() is called without force.
    'profile_ttl_hours' => (int) env('CONTACTS_PROFILE_TTL_HOURS', 24 * 7),
];
