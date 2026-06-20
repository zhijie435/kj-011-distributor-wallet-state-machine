<?php

namespace Tests\Feature;

use App\Enums\WalletStatus;
use App\Exceptions\StateTransitionException;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Models\User;
use App\Services\StateMachine\WalletStateMachine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletStateSyncTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletService = $this->app->make(WalletService::class);
    }

    public function test_state_transaction_updates_model_correctly(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::INACTIVE->value,
        ]);
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->activateWallet($wallet, '测试激活', $operator->id);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNotNull($updatedWallet->last_activated_at);
        $this->assertEquals($distributor->id, $updatedWallet->distributor_id);
        $this->assertEquals($distributor->name, $updatedWallet->distributor?->name);

        $this->assertDatabaseHas('dealer_wallets', [
            'id' => $wallet->id,
            'status' => WalletStatus::ACTIVE->value,
        ]);
    }

    public function test_consecutive_state_transitions_maintain_consistency(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::INACTIVE->value,
        ]);
        $operator = User::factory()->create();

        $wallet = $this->walletService->activateWallet($wallet, '激活', $operator->id);
        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);

        $wallet = $this->walletService->freezeWallet($wallet, '冻结', $operator->id);
        $this->assertEquals(WalletStatus::FROZEN, $wallet->status);
        $this->assertEquals('冻结', $wallet->freeze_reason);
        $this->assertNotNull($wallet->last_frozen_at);

        $wallet = $this->walletService->unfreezeWallet($wallet, '解冻', $operator->id);
        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);
        $this->assertNull($wallet->freeze_reason);

        $wallet = $this->walletService->restrictWallet($wallet, '限制', $operator->id);
        $this->assertEquals(WalletStatus::RESTRICTED, $wallet->status);
        $this->assertEquals('限制', $wallet->restrict_reason);

        $wallet = $this->walletService->unrestrictWallet($wallet, '解除限制', $operator->id);
        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);
        $this->assertNull($wallet->restrict_reason);

        $wallet->refresh();
        $this->assertEquals(0, $wallet->balance);
        $this->assertEquals(0, $wallet->frozen_amount);

        $wallet = $this->walletService->closeWallet($wallet, '注销', $operator->id);
        $this->assertEquals(WalletStatus::CLOSED, $wallet->status);
        $this->assertEquals('注销', $wallet->close_reason);
        $this->assertNotNull($wallet->closed_at);

        $this->assertEquals(6, $wallet->stateLogs()->count());
    }

    public function test_state_transaction_uses_latest_data_from_database(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::INACTIVE->value,
        ]);
        $operator = User::factory()->create();

        $stateMachine = new WalletStateMachine($wallet);

        DealerWallet::where('id', $wallet->id)->update([
            'status' => WalletStatus::ACTIVE->value,
        ]);

        $this->expectException(StateTransitionException::class);
        $this->expectExceptionMessage('不允许从「正常」变更为「正常」');

        $stateMachine->transitionTo(WalletStatus::ACTIVE);
    }

    public function test_state_machine_prevents_duplicate_transitions(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::ACTIVE->value,
        ]);
        $operator = User::factory()->create();

        DB::beginTransaction();
        try {
            $stateMachine1 = new WalletStateMachine($wallet);
            $stateMachine1->transitionTo(WalletStatus::FROZEN, [
                'reason' => '测试冻结',
                'operator_id' => $operator->id,
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        $wallet->refresh();
        $this->assertEquals(WalletStatus::FROZEN, $wallet->status);

        $stateMachine2 = new WalletStateMachine($wallet);
        $this->expectException(StateTransitionException::class);
        $stateMachine2->transitionTo(WalletStatus::FROZEN);
    }

    public function test_unfreeze_updates_both_status_and_frozen_amount(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::FROZEN->value,
            'balance' => 500.00,
            'frozen_amount' => 200.00,
            'freeze_reason' => '测试冻结',
        ]);
        $operator = User::factory()->create();

        $updatedWallet = $this->walletService->unfreezeWallet($wallet, '解冻', $operator->id);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertEquals(0, $updatedWallet->frozen_amount);
        $this->assertNull($updatedWallet->freeze_reason);
        $this->assertEquals(500.00, $updatedWallet->balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'unfreeze',
            'amount' => 200.00,
        ]);
    }

    public function test_close_wallet_validates_balance_within_transaction(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::ACTIVE->value,
            'balance' => 100.00,
            'frozen_amount' => 0,
        ]);
        $operator = User::factory()->create();

        $stateMachine = new WalletStateMachine($wallet);

        $this->expectException(StateTransitionException::class);
        $this->expectExceptionMessage('钱包余额不为0');

        $stateMachine->transitionTo(WalletStatus::CLOSED, [
            'reason' => '注销',
            'operator_id' => $operator->id,
        ]);
    }

    public function test_close_wallet_validates_frozen_amount_within_transaction(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::ACTIVE->value,
            'balance' => 0,
            'frozen_amount' => 50.00,
        ]);
        $operator = User::factory()->create();

        $stateMachine = new WalletStateMachine($wallet);

        $this->expectException(StateTransitionException::class);
        $this->expectExceptionMessage('钱包存在冻结金额');

        $stateMachine->transitionTo(WalletStatus::CLOSED, [
            'reason' => '注销',
            'operator_id' => $operator->id,
        ]);
    }

    public function test_state_machine_uses_fresh_data_from_database(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::INACTIVE->value,
        ]);
        $operator = User::factory()->create();

        $wallet->status = WalletStatus::ACTIVE;
        $stateMachine = new WalletStateMachine($wallet);

        $this->expectException(StateTransitionException::class);
        $this->expectExceptionMessage('不允许从「未激活」变更为「已冻结」');

        $stateMachine->transitionTo(WalletStatus::FROZEN, [
            'operator_id' => $operator->id,
        ]);
    }

    public function test_allowed_transitions_reflect_actual_state(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::INACTIVE->value,
        ]);

        $allowed1 = $wallet->getAllowedTransitions();
        $this->assertCount(2, $allowed1);
        $this->assertEquals('activate', $allowed1[0]['action']);
        $this->assertEquals('close', $allowed1[1]['action']);

        $wallet->status = WalletStatus::ACTIVE;
        $wallet->save();

        $wallet->refresh();
        $allowed2 = $wallet->getAllowedTransitions();
        $this->assertCount(3, $allowed2);
        $this->assertEquals('freeze', $allowed2[0]['action']);
        $this->assertEquals('restrict', $allowed2[1]['action']);
        $this->assertEquals('close', $allowed2[2]['action']);
    }

    public function test_close_with_balance_not_allowed_in_transitions(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::ACTIVE->value,
            'balance' => 100.00,
            'frozen_amount' => 0,
        ]);

        $allowed = $wallet->getAllowedTransitions();
        
        $hasClose = collect($allowed)->contains('action', 'close');
        $this->assertFalse($hasClose);
    }

    public function test_service_methods_return_consistent_data(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::ACTIVE->value,
            'balance' => 1000.00,
            'frozen_amount' => 200.00,
        ]);
        $operator = User::factory()->create();

        $balance = $this->walletService->getWalletBalance($wallet);

        $this->assertEquals($wallet->id, $balance['wallet_id']);
        $this->assertEquals(WalletStatus::ACTIVE->value, $balance['status']);
        $this->assertTrue($balance['is_active']);
        $this->assertFalse($balance['is_frozen']);
        $this->assertEquals(1000.00, $balance['total_balance']);
        $this->assertEquals(200.00, $balance['frozen_amount']);
        $this->assertEquals(800.00, $balance['available_balance']);
        $this->assertArrayHasKey('allowed_transitions', $balance);
        $this->assertArrayHasKey('can_freeze', $balance);
        $this->assertArrayHasKey('can_restrict', $balance);
    }

    public function test_multiple_service_calls_maintain_consistency(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::ACTIVE->value,
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $operator = User::factory()->create();

        $this->walletService->recharge($wallet, 1000.00, '充值', $operator->id);
        $wallet->refresh();

        $this->walletService->freezeAmount($wallet, 300.00, '冻结', $operator->id);
        $wallet->refresh();

        $this->assertEquals(1000.00, $wallet->balance);
        $this->assertEquals(300.00, $wallet->frozen_amount);
        $this->assertEquals(700.00, $wallet->getAvailableBalance());

        $this->walletService->freezeWallet($wallet, '钱包冻结', $operator->id);
        $wallet->refresh();

        $this->assertEquals(WalletStatus::FROZEN, $wallet->status);
        $this->assertEquals(1000.00, $wallet->balance);
        $this->assertEquals(300.00, $wallet->frozen_amount);

        $this->walletService->unfreezeWallet($wallet, '解冻', $operator->id);
        $wallet->refresh();

        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);
        $this->assertEquals(1000.00, $wallet->balance);
        $this->assertEquals(0, $wallet->frozen_amount);

        $this->walletService->deduct($wallet, 1000.00, \App\Enums\WalletTransactionType::WITHDRAW, '提现', $operator->id);
        $wallet->refresh();

        $this->assertEquals(0, $wallet->balance);

        $this->walletService->closeWallet($wallet, '注销', $operator->id);
        $wallet->refresh();

        $this->assertEquals(WalletStatus::CLOSED, $wallet->status);
        $this->assertEquals(0, $wallet->balance);
        $this->assertEquals(0, $wallet->frozen_amount);
    }
}
