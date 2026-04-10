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

    'ai' => [
        'base_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8088'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 120),
        'realtime_pose_url' => env('AI_REALTIME_POSE_URL'),
        'realtime_pose_timeout' => (int) env('AI_REALTIME_POSE_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | GPU inference service (Phase 3 — PyTorch/CUDA, separate deploy)
    |--------------------------------------------------------------------------
    |
    | Laravel never runs models: jobs call these HTTP endpoints asynchronously.
    | Defaults to AI_SERVICE_URL for local dev when GPU stack is co-located.
    |
    */
    'gpu' => [
        'base_url' => env('GPU_SERVICE_URL', env('AI_SERVICE_URL', 'http://127.0.0.1:8088')),
        'timeout' => (int) env('GPU_SERVICE_TIMEOUT', 180),
        'realtime_timeout' => (int) env('GPU_REALTIME_TIMEOUT', 30),
    ],

];
