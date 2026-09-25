<?php

return [
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),
    'verify_token' => env('META_VERIFY_TOKEN'),
    'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
    'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
    'instagram_graph_url' => env('META_INSTAGRAM_GRAPH_URL', 'https://graph.instagram.com'),

    // Reject webhook POSTs whose X-Hub-Signature-256 does not match. Only disable for local debugging.
    'verify_signature' => env('META_VERIFY_SIGNATURE', true),

    // Optional single-account bootstrap (prefer meta_accounts table). Used by `php artisan meta:account:import-env`.
    'page_id' => env('META_PAGE_ID'),
    'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
    'instagram_account_id' => env('META_INSTAGRAM_ACCOUNT_ID'),

    'http_timeout' => (int) env('META_HTTP_TIMEOUT', 15),

    // Outgoing text limits per platform (longer replies get split).
    'max_message_length' => [
        'facebook' => 2000,
        'instagram' => 1000,
    ],

    // How max_message_length is measured: Instagram counts UTF-8 bytes.
    'message_length_unit' => [
        'facebook' => 'chars',
        'instagram' => 'bytes',
    ],

    // Best-effort mark_seen + typing_on before bot replies (Messenger only; not documented for Instagram).
    'sender_actions' => env('META_SENDER_ACTIONS', true),

    // Webhook fields subscribed by MetaMessagingService::subscribeApp().
    'subscribed_fields' => [
        'facebook_login' => ['messages', 'messaging_postbacks', 'message_echoes'],
        'instagram_login' => ['messages', 'messaging_postbacks'],
    ],

    'queue' => env('META_QUEUE', 'default'),

    /*
     | "Continue with Facebook" (Facebook Login for Business, manual server-side flow).
     | Admin → Pages & Channels → Connect. Add the redirect URI shown in Settings → Connection
     | (/admin/meta-accounts/connect/facebook/callback) to "Valid OAuth Redirect URIs" in the Meta dashboard.
     | When META_LOGIN_CONFIG_ID is set (a Facebook Login for Business configuration), the dialog uses config_id
     | and the permissions chosen in that configuration; otherwise the scopes below are requested.
     */
    'login' => [
        'config_id' => env('META_LOGIN_CONFIG_ID'),
        'dialog_url' => env('META_DIALOG_URL', 'https://www.facebook.com'),
        'scopes' => [
            'pages_show_list',
            'pages_messaging',
            'pages_manage_metadata',
            'pages_read_engagement',
            'instagram_basic',
            'instagram_manage_messages',
            'business_management',
        ],
    ],
];
