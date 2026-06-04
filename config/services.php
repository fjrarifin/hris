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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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

    'whatsapp' => [
        'url' => env('WHATSAPP_API_URL'),
        'device_id' => env('WHATSAPP_DEVICE_ID'),
        'username' => env('WHATSAPP_API_USERNAME'),
        'password' => env('WHATSAPP_API_PASSWORD'),
        'attendance_group_id' => env('WHATSAPP_ATTENDANCE_GROUP_ID', '120363426462821941@g.us'),
        'hr_permission_group_id' => env('WHATSAPP_HR_PERMISSION_GROUP_ID', env('WHATSAPP_ATTENDANCE_GROUP_ID', '120363426462821941@g.us')),
        'attendance_warning_override_nik' => env('WHATSAPP_ATTENDANCE_WARNING_OVERRIDE_NIK'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen3:8b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 60),
    ],

    'public_approval' => [
        'base_url' => env('PUBLIC_APPROVAL_BASE_URL', 'https://hris.hompimplay.id'),
        'expires_hours' => (int) env('PUBLIC_APPROVAL_EXPIRES_HOURS', 18),
    ],

    'frontend' => [
        'base_url' => env('FRONTEND_URL', 'https://hr.hompimplay.id'),
    ],

    'hris_agent' => [
        'enabled' => env('HRIS_AGENT_ENABLED', true),
        'created_date' => env('HRIS_AGENT_CREATED_DATE', '2026-05-28'),
        'trigger_prefix' => env('HRIS_AGENT_TRIGGER_PREFIX'),
        'webhook_token' => env('HRIS_AGENT_WEBHOOK_TOKEN'),
        'allowed_senders' => array_values(array_filter(array_map(
            fn ($sender) => trim((string) $sender),
            explode(',', env('HRIS_AGENT_ALLOWED_SENDERS', ''))
        ))),
    ],

];
