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
        'enabled' => env('JIRA_ENABLED', false),
        'base_url' => env('JIRA_BASE_URL'),
        'email' => env('JIRA_EMAIL'),
        'api_token' => env('JIRA_API_TOKEN'),
        'project_key' => env('JIRA_PROJECT_KEY'),
        'issue_type' => env('JIRA_ISSUE_TYPE'),
        'ticket_base_url' => env('JIRA_TICKET_BASE_URL'),
        'bancada_ticket_base_url' => env('JIRA_BANCADA_TICKET_BASE_URL'),

        /*
         | Conta que figura como relator dos chamados abertos pelo CoreTI.
         |
         | Quem autentica é uma conta pessoal, e sem isto o chamado apareceria
         | como aberto por ela — o que confunde quem lê a fila e quebra a
         | automação no dia em que essa pessoa sair. Definindo o relator, o
         | chamado nasce em nome do sistema.
         |
         | Exige que a conta autenticada tenha "Modificar relator" no projeto.
         | Sem a permissão, o Jira recusa a criação — e é melhor recusar do
         | que abrir em nome errado sem avisar.
         |
         | Se um dia houver token da própria conta de suporte, ela passa a
         | autenticar e este campo vira redundante (inofensivo).
         */
        'reporter_account_id' => env('JIRA_REPORTER_ACCOUNT_ID'),
        'reporter_email' => env('JIRA_REPORTER_EMAIL'),
    ],

    'google_workspace' => [
        'service_account_json' => env('GOOGLE_WORKSPACE_SERVICE_ACCOUNT_JSON'),
        'admin_subject' => env('GOOGLE_WORKSPACE_ADMIN_SUBJECT'),
        'domain' => env('GOOGLE_WORKSPACE_DOMAIN'),
        'email_total_licenses' => env('GOOGLE_WORKSPACE_EMAIL_TOTAL_LICENSES'),
    ],

];
