<?php

/*
| Public legal pages (Privacy Policy, Terms, Data Deletion) and Meta data-deletion / deauthorize callbacks.
| Set APP_OPERATOR_NAME and APP_CONTACT_EMAIL in .env before submitting the app for Meta App Review.
*/
return [
    // The legal entity / business that operates the service (the "data controller").
    'operator_name' => env('APP_OPERATOR_NAME', 'Apex Growth Solutions'),

    // Public contact for privacy and deletion requests. Must be a monitored mailbox.
    'contact_email' => env('APP_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'privacy@example.com')),

    // Optional postal address / country shown on the legal pages.
    'operator_address' => env('APP_OPERATOR_ADDRESS'),

    // Product name shown to users and Meta reviewers (matches the Meta app name).
    'product_name' => env('APP_PRODUCT_NAME', 'Apex Chat Bot'),

    // Date shown as "Effective date" / "Last updated" on the legal pages (YYYY-MM-DD).
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-09-26'),

    // Retention periods stated in the Privacy Policy. Keep these in sync with your actual pruning jobs.
    'retention' => [
        'conversations_months' => (int) env('DATA_RETENTION_MONTHS', 12),
        'webhook_events_days' => (int) env('WEBHOOK_EVENT_RETENTION_DAYS', 30), // meta:prune-webhook-events
        'logs_days' => (int) env('LOG_RETENTION_DAYS', 14),
    ],

    // AI sub-processors that may receive message content to generate replies.
    'ai_subprocessors' => [
        ['name' => 'OpenAI, L.L.C.', 'purpose' => 'Generating suggested/automatic reply text', 'url' => 'https://openai.com/policies/privacy-policy'],
        ['name' => 'Anthropic, PBC', 'purpose' => 'Generating suggested/automatic reply text', 'url' => 'https://www.anthropic.com/legal/privacy'],
    ],

    // Reject Meta signed_request payloads whose issued_at is older than this many seconds (null = no limit).
    'signed_request_max_age' => env('META_SIGNED_REQUEST_MAX_AGE', 7 * 24 * 3600),
];
