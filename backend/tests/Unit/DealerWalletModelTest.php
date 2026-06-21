<?php

namespace Tests\Unit;

use App\Enums\WalletStatus;
use App\Models\DealerWallet;
use App\Models\Distributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealerWalletModelTest extends TestCase
{
    use RefreshDatabase;

    protected function createWallet(WalletStatus $status = WalletStatus::ACTIVE, array $attributes = []): DealerWallet
    {
        $distributor = Distributor::factory()->create();

        return DealerWallet::factory()->for($distributor)->create(array_merge([
            'status' => $status->value,
        ], $attributes));
    }

    public function test_is_active_returns_true_for_active_status(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $this->assertTrue($wallet->isActive());

        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $this->assertFalse($wallet->isActive());

        $wallet = $this->createWallet(WalletStatus::FROZEN);
        $this->assertFalse($wallet->isActive());

        $wallet = $this->createWallet(WalletStatus::RESTRICTED);
        $this->assertFalse($wallet->isActive());

        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $this->assertFalse($wallet->isActive());
    }

    public function test_is_frozen_returns_true_for_frozen_status(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN);
        $this->assertTrue($wallet->isFrozen());

        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $this->assertFalse($wallet->isFrozen());
    }

    public function test_is_restricted_returns_true_for_restricted_status(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED);
        $this->assertTrue($wallet->isRestricted());

        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $this->assertFalse($wallet->isRestricted());
    }

    public function test_is_inactive_returns_true_for_inactive_status(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);
        $this->assertTrue($wallet->isInactive());

        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $this->assertFalse($wallet->isInactive());
    }

    public function test_is_closed_returns_true_for_closed_status(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);
        $this->assertTrue($wallet->isClosed());

        $wallet = $this->createWallet(WalletStatus::ACTIVE);
        $this->assertFalse($wallet->isClosed());
    }

    public function test_get_available_balance_returns_balance_minus_frozen(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 1000.00,
            'frozen_amount' => 200.00,
        ]);

        $this->assertEquals(800.00, $wallet->getAvailableBalance());
    }

    public function test_get_available_balance_returns_zero_when_frozen_exceeds_balance(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 100.00,
            'frozen_amount' => 500.00,
        ]);

        $this->assertEquals(0, $wallet->getAvailableBalance());
    }

    public function test_get_available_balance_with_zero_frozen(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 1000.00,
            'frozen_amount' => 0,
        ]);

        $this->assertEquals(1000.00, $wallet->getAvailableBalance());
    }

    public function test_has_sufficient_balance_returns_true_when_enough(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 1000.00,
            'frozen_amount' => 200.00,
        ]);

        $this->assertTrue($wallet->hasSufficientBalance(500.00));
        $this->assertTrue($wallet->hasSufficientBalance(800.00));
    }

    public function test_has_sufficient_balance_returns_false_when_insufficient(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 1000.00,
            'frozen_amount' => 200.00,
        ]);

        $this->assertFalse($wallet->hasSufficientBalance(800.01));
        $this->assertFalse($wallet->hasSufficientBalance(1000.00));
    }

    public function test_scope_active_filters_active_wallets(): void
    {
        $active = $this->createWallet(WalletStatus::ACTIVE);
        $inactive = $this->createWallet(WalletStatus::INACTIVE);
        $frozen = $this->createWallet(WalletStatus::FROZEN);

        $result = DealerWallet::active()->get();

        $this->assertCount(1, $result);
        $this->assertEquals($active->id, $result->first()->id);
    }

    public function test_scope_frozen_filters_frozen_wallets(): void
    {
        $active = $this->createWallet(WalletStatus::ACTIVE);
        $frozen = $this->createWallet(WalletStatus::FROZEN);

        $result = DealerWallet::frozen()->get();

        $this->assertCount(1, $result);
        $this->assertEquals($frozen->id, $result->first()->id);
    }

    public function test_scope_by_status_filters_by_given_status(): void
    {
        $active = $this->createWallet(WalletStatus::ACTIVE);
        $restricted = $this->createWallet(WalletStatus::RESTRICTED);
        $closed = $this->createWallet(WalletStatus::CLOSED);

        $result = DealerWallet::byStatus(WalletStatus::RESTRICTED)->get();

        $this->assertCount(1, $result);
        $this->assertEquals($restricted->id, $result->first()->id);
    }

    public function test_get_allowed_transitions_for_inactive_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $transitions = $wallet->getAllowedTransitions();

        $this->assertCount(2, $transitions);
        $statuses = array_column($transitions, 'status');
        $this->assertContains(WalletStatus::ACTIVE->value, $statuses);
        $this->assertContains(WalletStatus::CLOSED->value, $statuses);
    }

    public function test_get_allowed_transitions_for_active_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);

        $transitions = $wallet->getAllowedTransitions();

        $this->assertCount(3, $transitions);
        $statuses = array_column($transitions, 'status');
        $this->assertContains(WalletStatus::FROZEN->value, $statuses);
        $this->assertContains(WalletStatus::RESTRICTED->value, $statuses);
        $this->assertContains(WalletStatus::CLOSED->value, $statuses);
    }

    public function test_get_allowed_transitions_excludes_close_when_balance_not_zero(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 100.00,
            'frozen_amount' => 0,
        ]);

        $transitions = $wallet->getAllowedTransitions();

        $statuses = array_column($transitions, 'status');
        $this->assertNotContains(WalletStatus::CLOSED->value, $statuses);
    }

    public function test_get_allowed_transitions_excludes_close_when_frozen_not_zero(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 50.00,
        ]);

        $transitions = $wallet->getAllowedTransitions();

        $statuses = array_column($transitions, 'status');
        $this->assertNotContains(WalletStatus::CLOSED->value, $statuses);
    }

    public function test_get_allowed_transitions_for_frozen_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::FROZEN, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);

        $transitions = $wallet->getAllowedTransitions();

        $this->assertCount(2, $transitions);
        $statuses = array_column($transitions, 'status');
        $this->assertContains(WalletStatus::ACTIVE->value, $statuses);
        $this->assertContains(WalletStatus::CLOSED->value, $statuses);
    }

    public function test_get_allowed_transitions_for_restricted_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::RESTRICTED, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);

        $transitions = $wallet->getAllowedTransitions();

        $this->assertCount(3, $transitions);
        $statuses = array_column($transitions, 'status');
        $this->assertContains(WalletStatus::ACTIVE->value, $statuses);
        $this->assertContains(WalletStatus::FROZEN->value, $statuses);
        $this->assertContains(WalletStatus::CLOSED->value, $statuses);
    }

    public function test_get_allowed_transitions_for_closed_wallet(): void
    {
        $wallet = $this->createWallet(WalletStatus::CLOSED);

        $transitions = $wallet->getAllowedTransitions();

        $this->assertCount(0, $transitions);
    }

    public function test_allowed_transitions_include_action_field(): void
    {
        $wallet = $this->createWallet(WalletStatus::INACTIVE);

        $transitions = $wallet->getAllowedTransitions();

        $activeTransition = collect($transitions)->firstWhere('status', WalletStatus::ACTIVE->value);
        $this->assertEquals('activate', $activeTransition['action']);

        $closeTransition = collect($transitions)->firstWhere('status', WalletStatus::CLOSED->value);
        $this->assertEquals('close', $closeTransition['action']);
    }

    public function test_allowed_transitions_include_label_and_color(): void
    {
        $wallet = $this->createWallet(WalletStatus::ACTIVE, [
            'balance' => 0,
            'frozen_amount' => 0,
        ]);

        $transitions = $wallet->getAllowedTransitions();

        foreach ($transitions as $transition) {
            $this->assertArrayHasKey('label', $transition);
            $this->assertArrayHasKey('color', $transition);
            $this->assertNotEmpty($transition['label']);
        }
    }

    public function test_wallet_has_distributor_relation(): void
    {
        $distributor = Distributor::factory()->create();
        $wallet = DealerWallet::factory()->for($distributor)->create();

        $this->assertTrue($wallet->distributor->is($distributor));
    }
}
