<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'jira' => [
        'ticket_base_url' => env('JIRA_TICKET_BASE_URL'),
        'bancada_ticket_base_url' => env('JIRA_BANCADA_TICKET_BASE_URL'),
    ],

    'google_workspace' => [
        'service_account_json' => env('GOOGLE_WORKSPACE_SERVICE_ACCOUNT_JSON'),
        'admin_subject' => env('GOOGLE_WORKSPACE_ADMIN_SUBJECT'),
        'domain' => env('GOOGLE_WORKSPACE_DOMAIN'),
        'email_total_licenses' => env('GOOGLE_WORKSPACE_EMAIL_TOTAL_LICENSES'),
    ],

];
