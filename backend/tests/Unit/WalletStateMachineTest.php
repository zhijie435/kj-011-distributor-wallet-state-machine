<?php

namespace Tests\Unit;

use App\Contracts\StateMachine\TransitionResult;
use App\Enums\WalletStatus;
use App\Enums\WalletTransitionAction;
use App\Exceptions\StateTransitionException;
use App\Models\DealerWallet;
use App\Models\Distributor;
use App\Models\User;
use App\Services\StateMachine\WalletStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function createWallet(WalletStatus $status = WalletStatus::INACTIVE, array $attributes = []): DealerWallet
    {
        $distributor = Distributor::factory()->create();

        return DealerWallet::factory()->for($distributor)->create(array_merge([
            'status' => $status->value,
        ], $attributes));
    }

    public function test_get_model_returns_dealer_wallet(): void
    {
        $wallet = $this->createWallet();
        $stateMachine = new WalletStateMachine($wallet);

        $this->assertSame($wallet, $stateMachine->getModel());
    }

    public function test_current_state_returns_wallet_status(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $this->assertEquals(WalletStatus::ACTIVE, $stateMachine->currentState());
    }

    public function test_can_transition_to_returns_true_for_valid_transition(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $this->assertTrue($stateMachine->canTransitionTo(WalletStatus::ACTIVE));
        $this->assertTrue($stateMachine->canTransitionTo(WalletStatus::CLOSED));
    }

    public function test_can_transition_to_returns_false_for_invalid_transition(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $this->assertFalse($stateMachine->canTransitionTo(WalletStatus::FROZEN));
        $this->assertFalse($stateMachine->canTransitionTo(WalletStatus::RESTRICTED));
        $this->assertFalse($stateMachine->canTransitionTo(WalletStatus::INACTIVE));
    }

    public function test_validate_transition_success_for_valid_transition(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $result = $stateMachine->validateTransition(WalletStatus::ACTIVE);

        $this->assertInstanceOf(TransitionResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isInvalid());
    }

    public function test_validate_transition_fails_for_terminal_state(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $stateMachine = new WalletStateMachine($wallet);

        $result = $stateMachine->validateTransition(WalletStatus::ACTIVE);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('is_terminal', $result->errors);
        $this->assertTrue($result->errors['is_terminal']);
    }

    public function test_validate_transition_fails_when_closing_with_balance(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['balance' => 100.00]);
        $stateMachine = new WalletStateMachine($wallet);

        $result = $stateMachine->validateTransition(WalletStatus::CLOSED);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('balance', $result->errors);
        $this->assertEquals(100.00, $result->errors['balance']);
    }

    public function test_validate_transition_fails_when_closing_with_frozen_amount(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, ['frozen_amount' => 50.00]);
        $stateMachine = new WalletStateMachine($wallet);

        $result = $stateMachine->validateTransition(WalletStatus::CLOSED);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('frozen_amount', $result->errors);
        $this->assertEquals(50.00, $result->errors['frozen_amount']);
    }

    public function test_validate_transition_fails_for_invalid_state_transition(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $result = $stateMachine->validateTransition(WalletStatus::FROZEN);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('from_state', $result->errors);
        $this->assertArrayHasKey('to_state', $result->errors);
        $this->assertArrayHasKey('allowed_states', $result->errors);
    }

    public function test_transition_to_throws_exception_for_terminal_state(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $stateMachine = new WalletStateMachine($wallet);

        $this->expectException(StateTransitionException::class);

        $stateMachine->transitionTo(WalletStatus::ACTIVE);
    }

    public function test_transition_to_throws_exception_for_invalid_transition(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $this->expectException(StateTransitionException::class);

        $stateMachine->transitionTo(WalletStatus::FROZEN);
    }

    public function test_transition_to_activate_from_inactive(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::ACTIVE, [
            'reason' => '测试激活',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNotNull($updatedWallet->last_activated_at);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'from_status' => WalletStatus::INACTIVE->value,
            'to_status' => WalletStatus::ACTIVE->value,
            'action' => WalletTransitionAction::ACTIVATE->value,
            'reason' => '测试激活',
            'operator_id' => $operator->id,
        ]);
    }

    public function test_transition_to_freeze_from_active(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::FROZEN, [
            'reason' => '违规操作',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
        $this->assertEquals('违规操作', $updatedWallet->freeze_reason);
        $this->assertNotNull($updatedWallet->last_frozen_at);
    }

    public function test_transition_to_unfreeze_auto_unfreezes_amount(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, [
            'frozen_amount' => 100.00,
            'freeze_reason' => '违规',
        ]);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::ACTIVE, [
            'reason' => '解冻',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertEquals(0, $updatedWallet->frozen_amount);
        $this->assertNull($updatedWallet->freeze_reason);
    }

    public function test_transition_to_restrict_from_active(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::RESTRICTED, [
            'reason' => '风险预警',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::RESTRICTED, $updatedWallet->status);
        $this->assertEquals('风险预警', $updatedWallet->restrict_reason);
        $this->assertNotNull($updatedWallet->last_restricted_at);
    }

    public function test_transition_to_freeze_from_restricted_clears_restrict_reason(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED, [
            'restrict_reason' => '风险预警',
        ]);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::FROZEN, [
            'reason' => '确认违规',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
        $this->assertEquals('确认违规', $updatedWallet->freeze_reason);
        $this->assertNull($updatedWallet->restrict_reason);
    }

    public function test_transition_to_unrestrict_clears_restrict_reason(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED, [
            'restrict_reason' => '风险预警',
        ]);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::ACTIVE, [
            'reason' => '解除风险',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNull($updatedWallet->restrict_reason);
    }

    public function test_transition_to_close_from_active(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::CLOSED, [
            'reason' => '经销商注销',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertEquals('经销商注销', $updatedWallet->close_reason);
        $this->assertNotNull($updatedWallet->closed_at);
    }

    public function test_transition_to_close_from_frozen_clears_freeze_reason(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, [
            'balance' => 0,
            'frozen_amount' => 0,
            'freeze_reason' => '违规',
        ]);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::CLOSED, [
            'reason' => '注销',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertNull($updatedWallet->freeze_reason);
        $this->assertNull($updatedWallet->restrict_reason);
    }

    public function test_transition_by_action_activate(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::ACTIVATE, ['reason' => '激活']);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
    }

    public function test_transition_by_action_freeze(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::FREEZE, ['reason' => '冻结']);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
    }

    public function test_transition_by_action_freeze_from_restricted(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED);
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::FREEZE_FROM_RESTRICTED, ['reason' => '冻结']);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
    }

    public function test_allowed_transitions_matches_enum(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $this->assertEquals(
            WalletStatus::ACTIVE->allowedTransitions(),
            $stateMachine->allowedTransitions()
        );
    }

    public function test_before_transition_hook_is_executed(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $hookExecuted = false;
        $stateMachine->beforeTransition(function () use (&$hookExecuted) {
            $hookExecuted = true;
        });

        $stateMachine->transitionTo(WalletStatus::ACTIVE);

        $this->assertTrue($hookExecuted);
    }

    public function test_after_transition_hook_is_executed(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $stateMachine = new WalletStateMachine($wallet);

        $hookExecuted = false;
        $stateMachine->afterTransition(function () use (&$hookExecuted) {
            $hookExecuted = true;
        });

        $stateMachine->transitionTo(WalletStatus::ACTIVE);

        $this->assertTrue($hookExecuted);
    }

    public function test_transition_persists_state_log(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $stateMachine->transitionTo(WalletStatus::FROZEN, [
            'reason' => '测试冻结',
            'operator_id' => $operator->id,
        ]);

        $this->assertDatabaseCount('wallet_state_logs', 1);
        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $wallet->id,
            'action' => WalletTransitionAction::FREEZE->value,
        ]);
    }

    public function test_transition_to_close_from_inactive(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::CLOSED, [
            'reason' => '放弃激活',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertEquals('放弃激活', $updatedWallet->close_reason);
        $this->assertNotNull($updatedWallet->closed_at);
    }

    public function test_transition_to_close_from_restricted(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED, [
            'balance' => 0,
            'frozen_amount' => 0,
            'restrict_reason' => '风险',
        ]);
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::CLOSED, [
            'reason' => '注销受限账户',
            'operator_id' => $operator->id,
        ]);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertNull($updatedWallet->restrict_reason);
        $this->assertEquals('注销受限账户', $updatedWallet->close_reason);
    }
}
