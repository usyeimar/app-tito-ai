<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tito AI Runners
|--------------------------------------------------------------------------
|
| Connection settings for the FastAPI runners microservice that manages
| voice agent sessions. The Laravel side never talks to LiveKit/Daily
| directly: it asks the runner to create a session and forwards the
| returned room URL + access token to the client.
|
| The runner decides which WebRTC transport (LiveKit/Daily) to use —
| Laravel does not send transport preference in the agent config.
|
| Source: services/runners
|
*/

return [
    'base_url' => rtrim((string) env('TITO_RUNNERS_URL', 'http://localhost:8000'), '/'),

    'api_key' => env('TITO_RUNNERS_API_KEY'),

    'timeout' => (int) env('TITO_RUNNERS_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Redis Communication
    |--------------------------------------------------------------------------
    |
    | Laravel dispatches commands to runners via Redis queues instead of HTTP.
    | The runner BRPOPs from `runner:commands` and pushes responses to
    | `runner:responses:{request_id}`.
    |
    */
    'redis' => [
        'connection' => env('TITO_RUNNERS_REDIS_CONNECTION', 'default'),
        'response_timeout' => (int) env('TITO_RUNNERS_RESPONSE_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Runner Registry (Load Balancing)
    |--------------------------------------------------------------------------
    |
    | When enabled, Laravel will use Redis to discover available runners and
    | balance new sessions across them based on current load (active_sessions).
    |
    | Requirements:
    | - RUNNER_ADVERTISE_URL must be set on each runner instance
    | - Redis must be accessible to both Laravel and runners
    |
    */
    'use_registry' => (bool) env('TITO_RUNNERS_USE_REGISTRY', false),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => (int) env('TITO_RUNNERS_CB_FAILURES', 5),
        'recovery_timeout' => (int) env('TITO_RUNNERS_CB_RECOVERY', 30),
        'success_threshold' => (int) env('TITO_RUNNERS_CB_SUCCESSES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Reconciliation
    |--------------------------------------------------------------------------
    |
    | Minutes after which an active session with no webhook events is
    | considered orphaned and marked as failed by the reconciliation command.
    |
    */
    'orphan_stale_minutes' => (int) env('TITO_RUNNERS_ORPHAN_MINUTES', 60),
];
