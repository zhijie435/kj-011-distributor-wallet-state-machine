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

    protected DealerWallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $distributor = Distributor::factory()->create();
        $this->wallet = DealerWallet::factory()->for($distributor)->create([
            'status' => WalletStatus::INACTIVE->value,
        ]);
    }

    public function test_get_model_returns_wallet(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $this->assertSame($this->wallet, $stateMachine->getModel());
    }

    public function test_current_state_returns_correct_status(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $this->assertEquals(WalletStatus::INACTIVE, $stateMachine->currentState());
    }

    public function test_can_transition_to_valid_state(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $this->assertTrue($stateMachine->canTransitionTo(WalletStatus::ACTIVE));
        $this->assertFalse($stateMachine->canTransitionTo(WalletStatus::FROZEN));
    }

    public function test_validate_transition_success(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $result = $stateMachine->validateTransition(WalletStatus::ACTIVE);

        $this->assertInstanceOf(TransitionResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isInvalid());
    }

    public function test_validate_transition_fails_for_terminal_state(): void
    {
        $this->wallet->status = WalletStatus::CLOSED;
        $stateMachine = new WalletStateMachine($this->wallet);

        $result = $stateMachine->validateTransition(WalletStatus::ACTIVE);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('is_terminal', $result->errors);
        $this->assertTrue($result->errors['is_terminal']);
    }

    public function test_validate_transition_fails_for_invalid_transition(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $result = $stateMachine->validateTransition(WalletStatus::FROZEN);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('from_state', $result->errors);
        $this->assertArrayHasKey('to_state', $result->errors);
        $this->assertArrayHasKey('allowed_states', $result->errors);
    }

    public function test_validate_transition_fails_for_close_with_balance(): void
    {
        $this->wallet->status = WalletStatus::ACTIVE;
        $this->wallet->balance = 100.00;
        $stateMachine = new WalletStateMachine($this->wallet);

        $result = $stateMachine->validateTransition(WalletStatus::CLOSED);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('balance', $result->errors);
    }

    public function test_validate_transition_fails_for_close_with_frozen_amount(): void
    {
        $this->wallet->status = WalletStatus::ACTIVE;
        $this->wallet->balance = 0;
        $this->wallet->frozen_amount = 50.00;
        $stateMachine = new WalletStateMachine($this->wallet);

        $result = $stateMachine->validateTransition(WalletStatus::CLOSED);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('frozen_amount', $result->errors);
    }

    public function test_transition_to_throws_exception_for_invalid_transition(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $this->expectException(StateTransitionException::class);

        $stateMachine->transitionTo(WalletStatus::FROZEN);
    }

    public function test_transition_to_updates_wallet_status(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionTo(WalletStatus::ACTIVE);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNotNull($updatedWallet->last_activated_at);
        $this->assertDatabaseHas('dealer_wallets', [
            'id' => $this->wallet->id,
            'status' => WalletStatus::ACTIVE->value,
        ]);
    }

    public function test_transition_to_creates_state_log(): void
    {
        $operator = User::factory()->create();
        $stateMachine = new WalletStateMachine($this->wallet);

        $stateMachine->transitionTo(WalletStatus::ACTIVE, [
            'reason' => '测试激活',
            'operator_id' => $operator->id,
        ]);

        $this->assertDatabaseHas('wallet_state_logs', [
            'wallet_id' => $this->wallet->id,
            'from_status' => WalletStatus::INACTIVE->value,
            'to_status' => WalletStatus::ACTIVE->value,
            'action' => WalletTransitionAction::ACTIVATE->value,
            'reason' => '测试激活',
            'operator_id' => $operator->id,
        ]);
    }

    public function test_transition_by_action_activate(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::ACTIVATE, [
            'reason' => '激活钱包',
        ]);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
    }

    public function test_transition_by_action_freeze(): void
    {
        $this->wallet->status = WalletStatus::ACTIVE;
        $this->wallet->save();
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::FREEZE, [
            'reason' => '违规操作',
        ]);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
        $this->assertEquals('违规操作', $updatedWallet->freeze_reason);
        $this->assertNotNull($updatedWallet->last_frozen_at);
    }

    public function test_transition_by_action_unfreeze(): void
    {
        $this->wallet->status = WalletStatus::FROZEN;
        $this->wallet->freeze_reason = '违规操作';
        $this->wallet->save();
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::UNFREEZE);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNull($updatedWallet->freeze_reason);
    }

    public function test_transition_by_action_restrict(): void
    {
        $this->wallet->status = WalletStatus::ACTIVE;
        $this->wallet->save();
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::RESTRICT, [
            'reason' => '风险预警',
        ]);

        $this->assertEquals(WalletStatus::RESTRICTED, $updatedWallet->status);
        $this->assertEquals('风险预警', $updatedWallet->restrict_reason);
        $this->assertNotNull($updatedWallet->last_restricted_at);
    }

    public function test_transition_by_action_unrestrict(): void
    {
        $this->wallet->status = WalletStatus::RESTRICTED;
        $this->wallet->restrict_reason = '风险预警';
        $this->wallet->save();
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::UNRESTRICT);

        $this->assertEquals(WalletStatus::ACTIVE, $updatedWallet->status);
        $this->assertNull($updatedWallet->restrict_reason);
    }

    public function test_transition_by_action_freeze_from_restricted(): void
    {
        $this->wallet->status = WalletStatus::RESTRICTED;
        $this->wallet->restrict_reason = '风险预警';
        $this->wallet->save();
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::FREEZE_FROM_RESTRICTED, [
            'reason' => '确认违规',
        ]);

        $this->assertEquals(WalletStatus::FROZEN, $updatedWallet->status);
        $this->assertEquals('确认违规', $updatedWallet->freeze_reason);
        $this->assertNull($updatedWallet->restrict_reason);
    }

    public function test_transition_by_action_close(): void
    {
        $this->wallet->status = WalletStatus::ACTIVE;
        $this->wallet->balance = 0;
        $this->wallet->frozen_amount = 0;
        $this->wallet->save();
        $stateMachine = new WalletStateMachine($this->wallet);

        $updatedWallet = $stateMachine->transitionByAction(WalletTransitionAction::CLOSE, [
            'reason' => '经销商注销',
        ]);

        $this->assertEquals(WalletStatus::CLOSED, $updatedWallet->status);
        $this->assertEquals('经销商注销', $updatedWallet->close_reason);
        $this->assertNotNull($updatedWallet->closed_at);
    }

    public function test_allowed_transitions(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $transitions = $stateMachine->allowedTransitions();

        $this->assertCount(2, $transitions);
        $this->assertContains(WalletStatus::ACTIVE, $transitions);
        $this->assertContains(WalletStatus::CLOSED, $transitions);
    }

    public function test_full_state_flow(): void
    {
        $stateMachine = new WalletStateMachine($this->wallet);

        $wallet = $stateMachine->transitionByAction(WalletTransitionAction::ACTIVATE);
        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);

        $wallet = $stateMachine->transitionByAction(WalletTransitionAction::RESTRICT, ['reason' => '风险']);
        $this->assertEquals(WalletStatus::RESTRICTED, $wallet->status);

        $wallet = $stateMachine->transitionByAction(WalletTransitionAction::FREEZE_FROM_RESTRICTED, ['reason' => '违规']);
        $this->assertEquals(WalletStatus::FROZEN, $wallet->status);

        $wallet = $stateMachine->transitionByAction(WalletTransitionAction::UNFREEZE);
        $this->assertEquals(WalletStatus::ACTIVE, $wallet->status);

        $wallet = $stateMachine->transitionByAction(WalletTransitionAction::CLOSE, ['reason' => '注销']);
        $this->assertEquals(WalletStatus::CLOSED, $wallet->status);

        $this->assertEquals(5, $wallet->stateLogs()->count());
    }
}
