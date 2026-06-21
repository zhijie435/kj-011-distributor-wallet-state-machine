<?php

namespace App\Exceptions;

use App\Enums\WalletStatus;
use App\Models\DealerWallet;

class WalletException extends BaseException
{
    protected int $httpCode = 422;

    protected string $errorCode = 'WALLET_ERROR';

    protected const ERROR_CODES = [
        'NOT_FOUND' => 'WALLET_NOT_FOUND',
        'ALREADY_EXISTS' => 'WALLET_ALREADY_EXISTS',
        'NOT_ACTIVE' => 'WALLET_NOT_ACTIVE',
        'FROZEN' => 'WALLET_FROZEN',
        'RESTRICTED' => 'WALLET_RESTRICTED',
        'CLOSED' => 'WALLET_CLOSED',
        'INVALID_AMOUNT' => 'INVALID_AMOUNT',
        'INSUFFICIENT_BALANCE' => 'INSUFFICIENT_BALANCE',
        'INSUFFICIENT_FROZEN' => 'INSUFFICIENT_FROZEN_AMOUNT',
        'EXCEEDS_CREDIT_LIMIT' => 'EXCEEDS_CREDIT_LIMIT',
        'EXCEEDS_MAX_RECHARGE' => 'EXCEEDS_MAX_SINGLE_RECHARGE',
        'EXCEEDS_MAX_WITHDRAW' => 'EXCEEDS_MAX_SINGLE_WITHDRAW',
        'BELOW_MIN_BALANCE' => 'BELOW_MIN_BALANCE',
        'BALANCE_NOT_ZERO' => 'BALANCE_NOT_ZERO',
        'FROZEN_NOT_ZERO' => 'FROZEN_AMOUNT_NOT_ZERO',
        'INVALID_OPERATION' => 'INVALID_WALLET_OPERATION',
        'TRANSACTION_NOT_FOUND' => 'TRANSACTION_NOT_FOUND',
        'DUPLICATE_TRANSACTION' => 'DUPLICATE_TRANSACTION',
    ];

    public function __construct(
        string $message,
        array $details = [],
        int $httpCode = 422,
        ?DealerWallet $wallet = null,
        ?string $operation = null,
    ) {
        parent::__construct($message);
        $this->details = $this->enrichDetails($details, $wallet, $operation);
        $this->httpCode = $httpCode;
    }

    protected function enrichDetails(array $details, ?DealerWallet $wallet, ?string $operation): array
    {
        if ($wallet) {
            $details += [
                'wallet_id' => $wallet->id,
                'wallet_no' => $wallet->wallet_no,
                'distributor_id' => $wallet->distributor_id,
                'current_status' => $wallet->status->value,
                'balance' => (float) $wallet->balance,
                'frozen_amount' => (float) $wallet->frozen_amount,
            ];
        }

        if ($operation) {
            $details['operation'] = $operation;
        }

        return $details;
    }

    protected static function make(
        string $message,
        string $errorCodeKey,
        array $details = [],
        int $httpCode = 422,
        ?DealerWallet $wallet = null,
        ?string $operation = null,
    ): self {
        $instance = new self($message, $details, $httpCode, $wallet, $operation);
        $instance->errorCode = self::ERROR_CODES[$errorCodeKey] ?? $errorCodeKey;
        $instance->details['error_code'] = $instance->errorCode;

        return $instance;
    }

    public static function walletNotFound(int $distributorId): self
    {
        return self::make('经销商钱包不存在', 'NOT_FOUND', [
            'distributor_id' => $distributorId,
        ], 404);
    }

    public static function walletNotFoundByWallet(int $walletId): self
    {
        return self::make('钱包不存在', 'NOT_FOUND', [
            'wallet_id' => $walletId,
        ], 404);
    }

    public static function walletAlreadyExists(int $distributorId, ?DealerWallet $wallet = null): self
    {
        return self::make('经销商钱包已存在', 'ALREADY_EXISTS', [
            'distributor_id' => $distributorId,
        ], 409, $wallet, 'create');
    }

    public static function walletNotActive(WalletStatus $status, ?DealerWallet $wallet = null): self
    {
        return self::make(
            "钱包状态非正常，当前状态：{$status->label()}",
            'NOT_ACTIVE',
            ['status' => $status->value, 'status_label' => $status->label()],
            422,
            $wallet,
            'operation_check'
        );
    }

    public static function frozenWallet(?DealerWallet $wallet = null): self
    {
        return self::make('钱包已冻结，无法操作', 'FROZEN', [], 422, $wallet, 'operation_check');
    }

    public static function restrictedWallet(?DealerWallet $wallet = null): self
    {
        return self::make('钱包已受限，无法操作', 'RESTRICTED', [], 422, $wallet, 'operation_check');
    }

    public static function closedWallet(?DealerWallet $wallet = null): self
    {
        return self::make('钱包已注销，无法操作', 'CLOSED', [], 422, $wallet, 'operation_check');
    }

    public static function invalidAmount(float $amount, string $operation = '', ?DealerWallet $wallet = null): self
    {
        return self::make('金额无效，必须大于0', 'INVALID_AMOUNT', [
            'amount' => $amount,
        ], 422, $wallet, $operation);
    }

    public static function insufficientBalance(
        float $required,
        float $available,
        ?DealerWallet $wallet = null,
        string $operation = 'deduct',
    ): self {
        return self::make('钱包可用余额不足', 'INSUFFICIENT_BALANCE', [
            'required' => $required,
            'available' => $available,
            'deficit' => bcsub((string) $required, (string) $available, 2),
        ], 422, $wallet, $operation);
    }

    public static function insufficientFrozenAmount(
        float $required,
        float $frozen,
        ?DealerWallet $wallet = null,
    ): self {
        return self::make('钱包冻结金额不足', 'INSUFFICIENT_FROZEN', [
            'required' => $required,
            'frozen_amount' => $frozen,
        ], 422, $wallet, 'unfreeze');
    }

    public static function exceedsCreditLimit(
        float $amount,
        float $limit,
        ?DealerWallet $wallet = null,
    ): self {
        return self::make('超出信用额度', 'EXCEEDS_CREDIT_LIMIT', [
            'amount' => $amount,
            'credit_limit' => $limit,
            'exceeded_by' => bcsub((string) $amount, (string) $limit, 2),
        ], 422, $wallet, 'credit_check');
    }

    public static function exceedsMaxSingleRecharge(
        float $amount,
        float $maxAmount,
        ?DealerWallet $wallet = null,
    ): self {
        return self::make('单笔充值金额超过上限', 'EXCEEDS_MAX_RECHARGE', [
            'amount' => $amount,
            'max_single_recharge' => $maxAmount,
        ], 422, $wallet, 'recharge');
    }

    public static function exceedsMaxSingleWithdraw(
        float $amount,
        float $maxAmount,
        ?DealerWallet $wallet = null,
    ): self {
        return self::make('单笔提现金额超过上限', 'EXCEEDS_MAX_WITHDRAW', [
            'amount' => $amount,
            'max_single_withdraw' => $maxAmount,
        ], 422, $wallet, 'withdraw');
    }

    public static function belowMinBalance(
        float $currentBalance,
        float $minBalance,
        ?DealerWallet $wallet = null,
        string $operation = 'withdraw',
    ): self {
        return self::make('操作后余额低于最低要求', 'BELOW_MIN_BALANCE', [
            'current_balance' => $currentBalance,
            'min_balance' => $minBalance,
        ], 422, $wallet, $operation);
    }

    public static function balanceNotZeroForClose(float $balance, ?DealerWallet $wallet = null): self
    {
        return self::make('钱包余额不为0，无法注销', 'BALANCE_NOT_ZERO', [
            'balance' => $balance,
        ], 422, $wallet, 'close');
    }

    public static function frozenAmountNotZeroForClose(float $frozenAmount, ?DealerWallet $wallet = null): self
    {
        return self::make('钱包存在冻结金额，无法注销', 'FROZEN_NOT_ZERO', [
            'frozen_amount' => $frozenAmount,
        ], 422, $wallet, 'close');
    }

    public static function invalidOperation(
        string $operation,
        WalletStatus $currentStatus,
        ?DealerWallet $wallet = null,
    ): self {
        return self::make(
            "当前状态「{$currentStatus->label()}」不允许执行「{$operation}」操作",
            'INVALID_OPERATION',
            ['operation' => $operation, 'current_status' => $currentStatus->value],
            422,
            $wallet,
            $operation
        );
    }

    public static function transactionNotFound(string $transactionNo): self
    {
        return self::make('交易记录不存在', 'TRANSACTION_NOT_FOUND', [
            'transaction_no' => $transactionNo,
        ], 404);
    }

    public static function duplicateTransaction(
        string $referenceType,
        int $referenceId,
        ?DealerWallet $wallet = null,
    ): self {
        return self::make('重复的交易请求', 'DUPLICATE_TRANSACTION', [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ], 409, $wallet, 'transaction');
    }
}
