<?php

namespace Tests\Unit;

use App\Enums\WalletStatus;
use App\Exceptions\StateTransitionException;
use App\Exceptions\WalletException;
use App\Models\DealerWallet;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletExceptionTest extends TestCase
{
    use RefreshDatabase;

    protected function createWallet(WalletStatus $status = WalletStatus::ACTIVE, array $attributes = []): DealerWallet
    {
        $distributor = Distributor::factory()->create();

        return DealerWallet::factory()->for($distributor)->create(array_merge([
            'status' => $status->value,
        ], $attributes));
    }

    public function test_wallet_not_found_exception(): void
    {
        $exception = WalletException::walletNotFound(123);

        $this->assertInstanceOf(WalletException::class, $exception);
        $this->assertEquals('经销商钱包不存在', $exception->getMessage());
        $this->assertEquals(404, $exception->getHttpCode());
        $this->assertEquals('WALLET_NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals(123, $exception->getDetails()['distributor_id']);
    }

    public function test_wallet_not_found_by_wallet_exception(): void
    {
        $exception = WalletException::walletNotFoundByWallet(456);

        $this->assertEquals('钱包不存在', $exception->getMessage());
        $this->assertEquals(404, $exception->getHttpCode());
        $this->assertEquals('WALLET_NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals(456, $exception->getDetails()['wallet_id']);
    }

    public function test_wallet_already_exists_exception(): void
    {
        $wallet = $this->createWallet();
        $exception = WalletException::walletAlreadyExists($wallet->distributor_id, $wallet);

        $this->assertEquals('经销商钱包已存在', $exception->getMessage());
        $this->assertEquals(409, $exception->getHttpCode());
        $this->assertEquals('WALLET_ALREADY_EXISTS', $exception->getErrorCode());
        $this->assertEquals('create', $exception->getDetails()['operation']);
        $this->assertEquals($wallet->id, $exception->getDetails()['wallet_id']);
    }

    public function test_wallet_not_active_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);
        $exception = WalletException::walletNotActive(WalletStatus::FROZEN, $wallet);

        $this->assertStringContainsString('钱包状态非正常', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('WALLET_NOT_ACTIVE', $exception->getErrorCode());
        $this->assertEquals('frozen', $exception->getDetails()['status']);
    }

    public function test_frozen_wallet_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);
        $exception = WalletException::frozenWallet($wallet);

        $this->assertEquals('钱包已冻结，无法操作', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('WALLET_FROZEN', $exception->getErrorCode());
        $this->assertEquals('operation_check', $exception->getDetails()['operation']);
    }

    public function test_restricted_wallet_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED);
        $exception = WalletException::restrictedWallet($wallet);

        $this->assertEquals('钱包已受限，无法操作', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('WALLET_RESTRICTED', $exception->getErrorCode());
    }

    public function test_closed_wallet_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $exception = WalletException::closedWallet($wallet);

        $this->assertEquals('钱包已注销，无法操作', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('WALLET_CLOSED', $exception->getErrorCode());
    }

    public function test_invalid_amount_exception(): void
    {
        $exception = WalletException::invalidAmount(-100.00, 'recharge');

        $this->assertEquals('金额无效，必须大于0', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('INVALID_AMOUNT', $exception->getErrorCode());
        $this->assertEquals(-100.00, $exception->getDetails()['amount']);
        $this->assertEquals('recharge', $exception->getDetails()['operation']);
    }

    public function test_insufficient_balance_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 50]);
        $exception = WalletException::insufficientBalance(200.00, 50.00, $wallet, 'deduct');

        $this->assertEquals('钱包可用余额不足', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('INSUFFICIENT_BALANCE', $exception->getErrorCode());
        $this->assertEquals(200.00, $exception->getDetails()['required']);
        $this->assertEquals(50.00, $exception->getDetails()['available']);
        $this->assertEquals(150.00, (float) $exception->getDetails()['deficit']);
    }

    public function test_insufficient_frozen_amount_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['frozen_amount' => 30]);
        $exception = WalletException::insufficientFrozenAmount(100.00, 30.00, $wallet);

        $this->assertEquals('钱包冻结金额不足', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('INSUFFICIENT_FROZEN_AMOUNT', $exception->getErrorCode());
        $this->assertEquals(100.00, $exception->getDetails()['required']);
        $this->assertEquals(30.00, $exception->getDetails()['frozen_amount']);
    }

    public function test_exceeds_credit_limit_exception(): void
    {
        $exception = WalletException::exceedsCreditLimit(200000.00, 100000.00);

        $this->assertEquals('超出信用额度', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('EXCEEDS_CREDIT_LIMIT', $exception->getErrorCode());
        $this->assertEquals(200000.00, $exception->getDetails()['amount']);
        $this->assertEquals(100000.00, $exception->getDetails()['credit_limit']);
        $this->assertEquals(100000.00, (float) $exception->getDetails()['exceeded_by']);
    }

    public function test_exceeds_max_single_recharge_exception(): void
    {
        $exception = WalletException::exceedsMaxSingleRecharge(600000.00, 500000.00);

        $this->assertEquals('单笔充值金额超过上限', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('EXCEEDS_MAX_SINGLE_RECHARGE', $exception->getErrorCode());
    }

    public function test_exceeds_max_single_withdraw_exception(): void
    {
        $exception = WalletException::exceedsMaxSingleWithdraw(300000.00, 200000.00);

        $this->assertEquals('单笔提现金额超过上限', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('EXCEEDS_MAX_SINGLE_WITHDRAW', $exception->getErrorCode());
    }

    public function test_below_min_balance_exception(): void
    {
        $exception = WalletException::belowMinBalance(-50.00, 0.00, null, 'withdraw');

        $this->assertEquals('操作后余额低于最低要求', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('BELOW_MIN_BALANCE', $exception->getErrorCode());
        $this->assertEquals(-50.00, $exception->getDetails()['current_balance']);
        $this->assertEquals(0.00, $exception->getDetails()['min_balance']);
    }

    public function test_balance_not_zero_for_close_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 100]);
        $exception = WalletException::balanceNotZeroForClose(100.00, $wallet);

        $this->assertEquals('钱包余额不为0，无法注销', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('BALANCE_NOT_ZERO', $exception->getErrorCode());
        $this->assertEquals(100.00, $exception->getDetails()['balance']);
    }

    public function test_frozen_amount_not_zero_for_close_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['frozen_amount' => 50]);
        $exception = WalletException::frozenAmountNotZeroForClose(50.00, $wallet);

        $this->assertEquals('钱包存在冻结金额，无法注销', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('FROZEN_AMOUNT_NOT_ZERO', $exception->getErrorCode());
        $this->assertEquals(50.00, $exception->getDetails()['frozen_amount']);
    }

    public function test_invalid_operation_exception(): void
    {
        $exception = WalletException::invalidOperation('freeze', WalletStatus::CLOSED);

        $this->assertStringContainsString('不允许执行', $exception->getMessage());
        $this->assertEquals(422, $exception->getHttpCode());
        $this->assertEquals('INVALID_WALLET_OPERATION', $exception->getErrorCode());
        $this->assertEquals('freeze', $exception->getDetails()['operation']);
        $this->assertEquals('closed', $exception->getDetails()['current_status']);
    }

    public function test_transaction_not_found_exception(): void
    {
        $exception = WalletException::transactionNotFound('TXN123456');

        $this->assertEquals('交易记录不存在', $exception->getMessage());
        $this->assertEquals(404, $exception->getHttpCode());
        $this->assertEquals('TRANSACTION_NOT_FOUND', $exception->getErrorCode());
        $this->assertEquals('TXN123456', $exception->getDetails()['transaction_no']);
    }

    public function test_duplicate_transaction_exception(): void
    {
        $exception = WalletException::duplicateTransaction('order', 123);

        $this->assertEquals('重复的交易请求', $exception->getMessage());
        $this->assertEquals(409, $exception->getHttpCode());
        $this->assertEquals('DUPLICATE_TRANSACTION', $exception->getErrorCode());
        $this->assertEquals('order', $exception->getDetails()['reference_type']);
        $this->assertEquals(123, $exception->getDetails()['reference_id']);
    }

    public function test_exception_render_returns_json_response(): void
    {
        $exception = WalletException::walletNotFound(123);
        $response = $exception->render();

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $data = $response->getData(true);

        $this->assertFalse($data['success']);
        $this->assertEquals('经销商钱包不存在', $data['message']);
        $this->assertEquals('WALLET_NOT_FOUND', $data['error_code']);
    }

    public function test_zero_amount_throws_invalid_amount_exception(): void
    {
        $exception = WalletException::invalidAmount(0, 'recharge');

        $this->assertEquals('INVALID_AMOUNT', $exception->getErrorCode());
        $this->assertEquals(0, $exception->getDetails()['amount']);
    }

    public function test_negative_amount_throws_invalid_amount_exception(): void
    {
        $exception = WalletException::invalidAmount(-50.00, 'deduct');

        $this->assertEquals('INVALID_AMOUNT', $exception->getErrorCode());
        $this->assertEquals(-50.00, $exception->getDetails()['amount']);
    }
}
