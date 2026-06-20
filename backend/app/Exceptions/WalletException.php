<?php

namespace App\Exceptions;

class WalletException extends BaseException
{
    protected int $httpCode = 422;

    protected string $errorCode = 'WALLET_ERROR';

    public function __construct(string $message, array $details = [], int $httpCode = 422)
    {
        parent::__construct($message);
        $this->details = $details;
        $this->httpCode = $httpCode;
    }

    public static function insufficientBalance(float $required, float $available): self
    {
        return new self('钱包余额不足', [
            'error_code' => 'INSUFFICIENT_BALANCE',
            'required' => $required,
            'available' => $available,
        ]);
    }

    public static function walletNotFound(int $distributorId): self
    {
        return new self('经销商钱包不存在', [
            'error_code' => 'WALLET_NOT_FOUND',
            'distributor_id' => $distributorId,
        ]);
    }

    public static function walletNotActive(string $status): self
    {
        return new self("钱包状态非正常，当前状态：{$status}", [
            'error_code' => 'WALLET_NOT_ACTIVE',
            'status' => $status,
        ]);
    }

    public static function walletAlreadyExists(int $distributorId): self
    {
        return new self('经销商钱包已存在', [
            'error_code' => 'WALLET_ALREADY_EXISTS',
            'distributor_id' => $distributorId,
        ]);
    }

    public static function restrictedWallet(): self
    {
        return new self('钱包已受限，无法操作', [
            'error_code' => 'WALLET_RESTRICTED',
        ]);
    }

    public static function frozenWallet(): self
    {
        return new self('钱包已冻结，无法操作', [
            'error_code' => 'WALLET_FROZEN',
        ]);
    }

    public static function closedWallet(): self
    {
        return new self('钱包已注销，无法操作', [
            'error_code' => 'WALLET_CLOSED',
        ]);
    }

    public static function invalidAmount(float $amount): self
    {
        return new self('金额无效，必须大于0', [
            'error_code' => 'INVALID_AMOUNT',
            'amount' => $amount,
        ]);
    }

    public static function exceedsCreditLimit(float $amount, float $limit): self
    {
        return new self('超出信用额度', [
            'error_code' => 'EXCEEDS_CREDIT_LIMIT',
            'amount' => $amount,
            'credit_limit' => $limit,
        ]);
    }
}
