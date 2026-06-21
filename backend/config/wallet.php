<?php

return [
    'min_balance' => env('WALLET_MIN_BALANCE', 0),
    'max_single_recharge' => env('WALLET_MAX_SINGLE_RECHARGE', 500000),
    'max_single_withdraw' => env('WALLET_MAX_SINGLE_WITHDRAW', 200000),
    'auto_freeze_on_negative' => env('WALLET_AUTO_FREEZE_ON_NEGATIVE', false),
    'state_log_retention_days' => env('WALLET_STATE_LOG_RETENTION_DAYS', 365),

    'default_currency' => env('WALLET_DEFAULT_CURRENCY', 'CNY'),
    'wallet_no_prefix' => env('WALLET_NO_PREFIX', 'W'),

    'queue' => [
        'connection' => env('WALLET_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'redis')),
        'state_transition_queue' => env('WALLET_STATE_TRANSITION_QUEUE', 'default'),
        'notification_queue' => env('WALLET_NOTIFICATION_QUEUE', 'default'),
        'state_transition_tries' => env('WALLET_STATE_TRANSITION_TRIES', 3),
        'state_transition_backoff' => env('WALLET_STATE_TRANSITION_BACKOFF', 30),
    ],

    'notification' => [
        'enabled' => env('WALLET_NOTIFICATION_ENABLED', true),
        'channel' => env('WALLET_NOTIFICATION_CHANNEL', 'log'),
    ],

    'health_check' => [
        'schedule' => env('WALLET_HEALTH_CHECK_SCHEDULE', 'daily'),
        'warn_negative_balance' => env('WALLET_WARN_NEGATIVE_BALANCE', true),
        'warn_inconsistent_freeze' => env('WALLET_WARN_INCONSISTENT_FREEZE', true),
        'warn_inactive_with_balance' => env('WALLET_WARN_INACTIVE_WITH_BALANCE', true),
    ],
];
