<?php

return [
    'mail_logo_url' => env('CORETI_MAIL_LOGO_URL'),

    'sync_notifications' => [
        'enabled' => filter_var(env('CORETI_SYNC_NOTIFICATIONS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'recipients' => array_values(array_filter(array_map(
            static fn (string $recipient): string => trim($recipient),
            explode(',', (string) env('CORETI_SYNC_NOTIFICATION_RECIPIENTS', env('MAIL_FROM_ADDRESS', '')))
        ), static fn (string $recipient): bool => filter_var($recipient, FILTER_VALIDATE_EMAIL))),
    ],
];
