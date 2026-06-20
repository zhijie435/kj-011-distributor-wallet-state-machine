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

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletService = $this->app->make(WalletService::class);
    }

    public function test_create_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $operator = User::factory()->create();

        $wallet = $this->walletService->createWallet($distributor, $operator->id);

        $this->assertInstanceOf(DealerWallet::class, $wallet);
        $this->assertEquals($distributor->id, $wallet->distributor_id);
        $this->assertEquals(WalletStatus::INACTIVE, $wallet->status);
        $this->assertEquals(0, $wallet->balance);
        $this->assertEquals(0, $wallet->frozen_amount);
        $this->assertEquals('CNY', $wallet->currency);
        $this->assertNotEmpty($wallet->wallet_no);
    }

    public function test_create_wallet_throws_exception_when_wallet_exists(): void
    {
        $distributor = Distributor::factory()->create();
        DealerWallet::factory()->for($distributor)->create();

        $this->expectException(WalletException::class);

        $this->walletService->createWallet($distributor);
    }

    public function test_activate_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create();
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->activateWallet($wallet, '测试激活', $operator->id);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNotNull($updatedWallet->last_activated_at);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => 'activate',
            'reason' => '测试激活',
            'operator_id' => $operator->id,
        ]);
    }

    public function test_freeze_wallet_from_active(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create();
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->freezeWallet($wallet, '违规操作', $operator->id);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
        $this->assertEquals('违规操作', $updatedWallet->freeze_reason);
        $this->assertNotNull($updatedWallet->last_frozen_at);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => 'freeze',
            'reason' => '违规操作',
        ]);
    }

    public function test_freeze_wallet_from_restricted(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->restricted()->create();
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->freezeWallet($wallet, '确认违规', $operator->id);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
        $this->assertEquals('确认违规', $updatedWallet->freeze_reason);
        $this->assertNull($updatedWallet->restrict_reason);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => 'freeze_from_restricted',
            'reason' => '确认违规',
        ]);
    }

    public function test_unfreeze_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->frozen()->create([
            'frozen_amount' => 100.00,
        ]);
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->unfreezeWallet($wallet, '解冻', $operator->id);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertEquals(0, $updatedWallet->frozen_amount);
        $this->assertNull($updatedWallet->freeze_reason);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => 'unfreeze',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => WalletTransactionType::UNFREEZE->value,
            'amount' => 100.00,
        ]);
    }

    public function test_restrict_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create();
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->restrictWallet($wallet, '风险预警', $operator->id);

        $this->assertEquals(WalletStatus::RESTRICTED, $updatedWallet->status);
        $this->assertEquals('风险预警', $updatedWallet->restrict_reason);
        $this->assertNotNull($updatedWallet->last_restricted_at);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => 'restrict',
            'reason' => '风险预警',
        ]);
    }

    public function test_unrestrict_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->restricted()->create();
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->unrestrictWallet($wallet, '解除风险', $operator->id);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNull($updatedWallet->restrict_reason);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => 'unrestrict',
        ]);
    }

    public function test_close_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->closeWallet($wallet, '经销商注销', $operator->id);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertEquals('经销商注销', $updatedWallet->close_reason);
        $this->assertNotNull($updatedWallet->closed_at);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => 'close',
            'reason' => '经销商注销',
        ]);
    }

    public function test_close_wallet_throws_exception_with_balance(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 100.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->closeWallet($wallet, '注销');
    }

    public function test_close_wallet_throws_exception_with_frozen_amount(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 0,
            'frozen_amount' => 50.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->closeWallet($wallet, '注销');
    }

    public function test_recharge_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 100.00,
        ]);
        $operator = User::factory()->create();

        $transaction = $this->walletService->recharge($wallet, 500.00, '充值测试', $operator->id);

        $this->assertEquals(WalletTransactionType::RECHARGE, $transaction->type);
        $this->assertEquals(500.00, $transaction->amount);
        $this->assertEquals(100.00, $transaction->balance_before);
        $this->assertEquals(600.00, $transaction->balance_after);
        $this->assertEquals('充值测试', $transaction->remark);
        $this->assertEquals($operator->id, $transaction->operator_id);

        $wallet->refresh();
        $this->assertEquals(600.00, $wallet->balance);
    }

    public function test_recharge_throws_exception_for_closed_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->closed()->create();

        $this->expectException(WalletException::class);

        $this->walletService->recharge($wallet, 100.00);
    }

    public function test_recharge_throws_exception_for_frozen_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->frozen()->create();

        $this->expectException(WalletException::class);

        $this->walletService->recharge($wallet, 100.00);
    }

    public function test_deduct_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 500.00,
        ]);
        $operator = User::factory()->create();

        $transaction = $this->walletService->deduct(
            $wallet,
            200.00,
            WalletTransactionType::PAYMENT,
            '消费扣款',
            $operator->id,
            'order',
            123
        );

        $this->assertEquals(WalletTransactionType::PAYMENT, $transaction->type);
        $this->assertEquals(-200.00, $transaction->amount);
        $this->assertEquals(500.00, $transaction->balance_before);
        $this->assertEquals(300.00, $transaction->balance_after);
        $this->assertEquals('order', $transaction->reference_type);
        $this->assertEquals(123, $transaction->reference_id);

        $wallet->refresh();
        $this->assertEquals(300.00, $wallet->balance);
    }

    public function test_deduct_throws_exception_for_insufficient_balance(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 100.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->deduct($wallet, 200.00, WalletTransactionType::PAYMENT);
    }

    public function test_freeze_amount(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 500.00,
            'frozen_amount' => 0,
        ]);
        $operator = User::factory()->create();

        $transaction = $this->walletService->freezeAmount($wallet, 200.00, '冻结测试', $operator->id);

        $this->assertEquals(WalletTransactionType::FREEZE, $transaction->type);
        $this->assertEquals(200.00, $transaction->amount);

        $wallet->refresh();
        $this->assertEquals(500.00, $wallet->balance);
        $this->assertEquals(200.00, $wallet->frozen_amount);
        $this->assertEquals(300.00, $wallet->getAvailableBalance());
    }

    public function test_freeze_amount_throws_exception_for_insufficient_balance(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 100.00,
            'frozen_amount' => 50.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->freezeAmount($wallet, 100.00);
    }

    public function test_unfreeze_amount(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 500.00,
            'frozen_amount' => 200.00,
        ]);
        $operator = User::factory()->create();

        $transaction = $this->walletService->unfreezeAmount($wallet, 150.00, '解冻测试', $operator->id);

        $this->assertEquals(WalletTransactionType::UNFREEZE, $transaction->type);
        $this->assertEquals(150.00, $transaction->amount);

        $wallet->refresh();
        $this->assertEquals(50.00, $wallet->frozen_amount);
        $this->assertEquals(450.00, $wallet->getAvailableBalance());
    }

    public function test_unfreeze_amount_more_than_frozen(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 500.00,
            'frozen_amount' => 100.00,
        ]);

        $transaction = $this->walletService->unfreezeAmount($wallet, 200.00);

        $this->assertEquals(100.00, $transaction->amount);

        $wallet->refresh();
        $this->assertEquals(0, $wallet->frozen_amount);
    }

    public function test_get_wallet_balance(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create([
            'balance' => 500.00,
            'frozen_amount' => 100.00,
            'credit_limit' => 1000.00,
        ]);

        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertEquals($wallet->id, $balance['wallet_id']);
        $this->assertEquals($wallet->wallet_no, $balance['wallet_no']);
        $this->assertEquals(WalletStatus::ACTIVE->value, $balance['status']);
        $this->assertEquals(500.00, $balance['total_balance']);
        $this->assertEquals(100.00, $balance['frozen_amount']);
        $this->assertEquals(400.00, $balance['available_balance']);
        $this->assertEquals(1000.00, $balance['credit_limit']);
    }

    public function test_list_wallets_with_filters(): void
    {
        $distributor1 = Distributor::factory()->create(['name' => '测试经销商A']);
        $distributor2 = Distributor::factory()->create(['name' => '经销商B']);

        $wallet1 = DealerWallet::factory()->for($distributor1)->active()->create();
        $wallet2 = DealerWallet::factory()->for($distributor2)->frozen()->create();
        $wallet3 = DealerWallet::factory()->create(['status' => WalletStatus::INACTIVE->value]);

        $result = $this->walletService->listWallets(['status' => WalletStatus::ACTIVE->value]);
        $this->assertEquals(1, $result->total());

        $result = $this->walletService->listWallets(['distributor_id' => $distributor1->id]);
        $this->assertEquals(1, $result->total());

        $result = $this->walletService->listWallets(['search' => '测试']);
        $this->assertEquals(1, $result->total());
    }

    public function test_get_statistics(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->active()->create();
        $operator = User::factory()->create();

        $this->walletService->recharge($wallet, 1000.00, '充值', $operator->id);
        $wallet->refresh();
        $this->walletService->deduct($wallet, 300.00, WalletTransactionType::PAYMENT, '消费', $operator->id);
        $wallet->refresh();
        $this->walletService->deduct($wallet, 100.00, WalletTransactionType::FEE, '手续费', $operator->id);

        $stats = $this->walletService->getStatistics($wallet);

        $this->assertEquals(1000.00, $stats['income']);
        $this->assertEquals(400.00, $stats['expense']);
        $this->assertEquals(600.00, $stats['net_flow']);
    }

    public function test_full_wallet_lifecycle(): void
    {
        $distributor = Distributor::factory()->create();
        $operator = User::factory()->create();

        $wallet = $this->walletService->createWallet($distributor, $operator->id);
        $this->assertEquals(WalletStatus::INACTIVE, $wallet->status);

        $wallet = $this->walletService->activateWallet($wallet, '激活', $operator->id);
        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);

        $this->walletService->recharge($wallet, 1000.00, '充值', $operator->id);
        $wallet->refresh();
        $this->assertEquals(1000.00, $wallet->balance);

        $wallet = $this->walletService->restrictWallet($wallet, '风险预警', $operator->id);
        $this->assertEquals(WalletStatus::RESTRICTED, $wallet->status);

        $wallet = $this->walletService->freezeWallet($wallet, '确认违规', $operator->id);
        $this->assertEquals(WalletStatus::FROZEN, $wallet->status);

        $wallet = $this->walletService->unfreezeWallet($wallet, '解冻', $operator->id);
        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);

        $this->walletService->deduct($wallet, 1000.00, WalletTransactionType::WITHDRAW, '提现', $operator->id);
        $wallet->refresh();
        $this->assertEquals(0, $wallet->balance);

        $wallet = $this->walletService->closeWallet($wallet, '注销', $operator->id);
        $this->assertEquals(WalletStatus::CLOSED, $wallet->status);

        $this->assertEquals(5, $wallet->stateLogs()->count());
        $this->assertEquals(2, $wallet->transactions()->count());
    }

    public function test_recharge_throws_exception_for_restricted_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->restricted()->create();

        $this->expectException(WalletException::class);

        $this->walletService->recharge($wallet, 100.00);
    }

    public function test_deduct_throws_exception_for_restricted_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->restricted()->create([
            'balance' => 500.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->deduct($wallet, 100.00, WalletTransactionType::PAYMENT);
    }

    public function test_freeze_amount_throws_exception_for_restricted_wallet(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->restricted()->create([
            'balance' => 500.00,
        ]);

        $this->expectException(WalletException::class);

        $this->walletService->freezeAmount($wallet, 100.00);
    }

    public function test_close_wallet_from_frozen_clears_freeze_reason(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->frozen()->create([
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->closeWallet($wallet, '注销冻结账户', $operator->id);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertNull($updatedWallet->freeze_reason);
        $this->assertEquals('注销冻结账户', $updatedWallet->close_reason);
    }

    public function test_close_wallet_from_restricted_clears_restrict_reason(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->restricted()->create([
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->closeWallet($wallet, '注销受限账户', $operator->id);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertNull($updatedWallet->restrict_reason);
        $this->assertEquals('注销受限账户', $updatedWallet->close_reason);
    }

    public function test_close_wallet_from_inactive_clears_reasons(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create();
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->closeWallet($wallet, '注销未激活账户', $operator->id);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertNull($updatedWallet->freeze_reason);
        $this->assertNull($updatedWallet->restrict_reason);
        $this->assertEquals('注销未激活账户', $updatedWallet->close_reason);
    }
}
