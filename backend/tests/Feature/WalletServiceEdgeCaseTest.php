<?php

namespace Tests\Feature;

use App\Enums\WalletStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\WalletException;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = $this->app->make(WalletService::class);
    }

    protected function createWallet(WalletStatus $status = WalletStatus::ACTIVE, array $attributes = []): DealerWallet
    {
        $distributor = Distributor::factory()->create();

        return DealerWallet::factory()->for($distributor)->create(array_merge([
            'status' => $status->value,
        ], $attributes));
    }

    public function test_recharge_with_zero_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('金额无效，必须大于0');

        $this->walletService->recharge($wallet, 0);
    }

    public function test_recharge_with_negative_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $this->expectException(WalletException::class);

        $this->walletService->recharge($wallet, -100.00);
    }

    public function test_recharge_exceeds_max_single_recharge_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('单笔充值金额超过上限');

        $this->walletService->recharge($wallet, 600000.00);
    }

    public function test_recharge_on_closed_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('钱包已注销，无法操作');

        $this->walletService->recharge($wallet, 100.00);
    }

    public function test_deduct_with_zero_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->deduct($wallet, 0, WalletTransactionType::PAYMENT);
    }

    public function test_deduct_with_negative_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->deduct($wallet, -50.00, WalletTransactionType::PAYMENT);
    }

    public function test_deduct_on_frozen_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, ['balance' => 500.00]);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('钱包已冻结，无法操作');

        $this->walletService->deduct($wallet, 100.00, WalletTransactionType::PAYMENT);
    }

    public function test_deduct_on_closed_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->deduct($wallet, 100.00, WalletTransactionType::PAYMENT);
    }

    public function test_deduct_duplicate_transaction_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 500.00]);
        $operator = User::factory()->create();

        $this->walletService->deduct(
            $wallet,
            100.00,
            WalletTransactionType::PAYMENT,
            '消费',
            $operator->id,
            'order',
            123
        );

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('重复的交易请求');

        $this->walletService->deduct(
            $wallet,
            100.00,
            WalletTransactionType::PAYMENT,
            '消费',
            $operator->id,
            'order',
            123
        );
    }

    public function test_freeze_amount_with_zero_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->freezeAmount($wallet, 0);
    }

    public function test_freeze_amount_with_negative_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->freezeAmount($wallet, -50.00);
    }

    public function test_freeze_amount_on_frozen_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->freezeAmount($wallet, 100.00);
    }

    public function test_freeze_amount_on_closed_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->freezeAmount($wallet, 100.00);
    }

    public function test_unfreeze_amount_with_zero_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 500.00,
            'frozen_amount' => 200.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->unfreezeAmount($wallet, 0);
    }

    public function test_unfreeze_amount_with_negative_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 500.00,
            'frozen_amount' => 200.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->unfreezeAmount($wallet, -50.00);
    }

    public function test_withdraw_exceeds_max_single_withdraw_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 500000.00]);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('单笔提现金额超过上限');

        $this->walletService->withdraw($wallet, 300000.00);
    }

    public function test_withdraw_with_zero_amount_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->withdraw($wallet, 0);
    }

    public function test_withdraw_on_frozen_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->withdraw($wallet, 100.00);
    }

    public function test_withdraw_on_restricted_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED, ['balance' => 500.00]);

        $this->expectException(WalletException::class);

        $this->walletService->withdraw($wallet, 100.00);
    }

    public function test_activate_already_active_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->activateWallet($wallet);
    }

    public function test_activate_frozen_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->activateWallet($wallet);
    }

    public function test_freeze_already_frozen_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->freezeWallet($wallet, '再次冻结');
    }

    public function test_freeze_inactive_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->freezeWallet($wallet, '冻结未激活钱包');
    }

    public function test_unfreeze_active_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->unfreezeWallet($wallet);
    }

    public function test_unfreeze_inactive_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->unfreezeWallet($wallet);
    }

    public function test_restrict_inactive_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->restrictWallet($wallet, '限制未激活钱包');
    }

    public function test_restrict_frozen_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->restrictWallet($wallet, '限制已冻结钱包');
    }

    public function test_restrict_already_restricted_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->restrictWallet($wallet, '再次限制');
    }

    public function test_unrestrict_active_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->unrestrictWallet($wallet);
    }

    public function test_unrestrict_frozen_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->unrestrictWallet($wallet);
    }

    public function test_close_closed_wallet_throws_exception(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);

        $this->expectException(\App\Exceptions\StateTransitionException::class);

        $this->walletService->closeWallet($wallet, '再次注销');
    }

    public function test_get_wallet_balance_can_freeze_for_active_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertTrue($balance['can_freeze']);
        $this->assertTrue($balance['can_restrict']);
        $this->assertFalse($balance['can_unfreeze']);
        $this->assertFalse($balance['can_unrestrict']);
    }

    public function test_get_wallet_balance_can_unfreeze_for_frozen_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);
        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertTrue($balance['can_unfreeze']);
        $this->assertFalse($balance['can_freeze']);
        $this->assertFalse($balance['can_restrict']);
    }

    public function test_get_wallet_balance_can_unrestrict_and_freeze_for_restricted_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED);
        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertTrue($balance['can_unrestrict']);
        $this->assertTrue($balance['can_freeze']);
        $this->assertFalse($balance['can_unfreeze']);
    }

    public function test_get_wallet_balance_can_close_when_zero_balance_and_frozen(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertTrue($balance['can_close']);
    }

    public function test_get_wallet_balance_cannot_close_with_balance(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 100.00,
            'frozen_amount' => 0,
        ]);
        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertFalse($balance['can_close']);
    }

    public function test_get_wallet_balance_cannot_close_with_frozen_amount(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 50.00,
        ]);
        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertFalse($balance['can_close']);
    }

    public function test_get_wallet_balance_returns_all_required_fields(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 500.00,
            'frozen_amount' => 100.00,
        ]);

        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertArrayHasKey('wallet_id', $balance);
        $this->assertArrayHasKey('wallet_no', $balance);
        $this->assertArrayHasKey('status', $balance);
        $this->assertArrayHasKey('status_label', $balance);
        $this->assertArrayHasKey('status_color', $balance);
        $this->assertArrayHasKey('is_active', $balance);
        $this->assertArrayHasKey('is_frozen', $balance);
        $this->assertArrayHasKey('is_restricted', $balance);
        $this->assertArrayHasKey('is_inactive', $balance);
        $this->assertArrayHasKey('is_closed', $balance);
        $this->assertArrayHasKey('total_balance', $balance);
        $this->assertArrayHasKey('frozen_amount', $balance);
        $this->assertArrayHasKey('available_balance', $balance);
        $this->assertArrayHasKey('credit_limit', $balance);
        $this->assertArrayHasKey('allowed_transitions', $balance);
        $this->assertArrayHasKey('allowed_actions', $balance);
        $this->assertArrayHasKey('can_activate', $balance);
        $this->assertArrayHasKey('can_freeze', $balance);
        $this->assertArrayHasKey('can_unfreeze', $balance);
        $this->assertArrayHasKey('can_restrict', $balance);
        $this->assertArrayHasKey('can_unrestrict', $balance);
        $this->assertArrayHasKey('can_close', $balance);
    }

    public function test_get_wallet_balance_allowed_actions_for_inactive(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $balance = $this->walletService->getWalletBalance($wallet);

        $actionValues = array_column($balance['allowed_actions'], 'action');
        $this->assertContains('activate', $actionValues);
    }

    public function test_get_wallet_balance_allowed_actions_for_active(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $balance = $this->walletService->getWalletBalance($wallet);

        $actionValues = array_column($balance['allowed_actions'], 'action');
        $this->assertContains('freeze', $actionValues);
        $this->assertContains('restrict', $actionValues);
        $this->assertContains('close', $actionValues);
    }

    public function test_get_wallet_balance_allowed_actions_for_frozen(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $balance = $this->walletService->getWalletBalance($wallet);

        $actionValues = array_column($balance['allowed_actions'], 'action');
        $this->assertContains('unfreeze', $actionValues);
        $this->assertContains('close', $actionValues);
    }

    public function test_get_wallet_balance_allowed_actions_for_restricted(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $balance = $this->walletService->getWalletBalance($wallet);

        $actionValues = array_column($balance['allowed_actions'], 'action');
        $this->assertContains('unrestrict', $actionValues);
        $this->assertContains('freeze_from_restricted', $actionValues);
        $this->assertContains('close', $actionValues);
    }

    public function test_get_wallet_balance_allowed_actions_for_closed(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertEmpty($balance['allowed_actions']);
    }

    public function test_min_balance_validation_on_withdraw(): void
    {
        config()->set('wallet.min_balance', 100);
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 200.00]);

        $this->expectException(WalletException::class);
        $this->expectExceptionMessage('操作后余额低于最低要求');

        $this->walletService->withdraw($wallet, 150.00);
    }

    public function test_min_balance_validation_passes_when_config_zero(): void
    {
        config()->set('wallet.min_balance', 0);
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 100.00]);
        $operator = User::factory()->create();

        $transaction = $this->walletService->withdraw($wallet, 100.00, '全部提现', $operator->id);

        $this->assertNotNull($transaction);
        $wallet->refresh();
        $this->assertEquals(0, $wallet->balance);
    }
}
