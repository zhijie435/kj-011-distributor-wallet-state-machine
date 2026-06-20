<?php

return [
    'min_balance' => env('WALLET_MIN_BALANCE', 0),
    'max_single_recharge' => env('WALLET_MAX_SINGLE_RECHARGE', 500000),
    'max_single_withdraw' => env('WALLET_MAX_SINGLE_WITHDRAW', 200000),
    'auto_freeze_on_negative' => env('WALLET_AUTO_FREEZE_ON_NEGATIVE', false),
    'state_log_retention_days' => env('WALLET_STATE_LOG_RETENTION_DAYS', 365),
];
