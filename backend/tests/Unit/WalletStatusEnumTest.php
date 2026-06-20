<?php

namespace Tests\Unit;

use App\Enums\WalletStatus;
use Tests\TestCase;

class WalletStatusEnumTest extends TestCase
{
    public function test_all_statuses_have_correct_values(): void
    {
        $this->assertEquals('inactive', WalletStatus::INACTIVE->value);
        $this->assertEquals('active', WalletStatus::ACTIVE->value);
        $this->assertEquals('frozen', WalletStatus::FROZEN->value);
        $this->assertEquals('restricted', WalletStatus::RESTRICTED->value);
        $this->assertEquals('closed', WalletStatus::CLOSED->value);
    }

    public function test_status_labels_are_correct(): void
    {
        $this->assertEquals('未激活', WalletStatus::INACTIVE->label());
        $this->assertEquals('正常', WalletStatus::ACTIVE->label());
        $this->assertEquals('已冻结', WalletStatus::FROZEN->label());
        $this->assertEquals('受限', WalletStatus::RESTRICTED->label());
        $this->assertEquals('已注销', WalletStatus::CLOSED->label());
    }

    public function test_is_final_status(): void
    {
        $this->assertFalse(WalletStatus::INACTIVE->isFinal());
        $this->assertFalse(WalletStatus::ACTIVE->isFinal());
        $this->assertFalse(WalletStatus::FROZEN->isFinal());
        $this->assertFalse(WalletStatus::RESTRICTED->isFinal());
        $this->assertTrue(WalletStatus::CLOSED->isFinal());
    }

    public function test_status_helper_methods(): void
    {
        $this->assertTrue(WalletStatus::INACTIVE->isInactive());
        $this->assertTrue(WalletStatus::ACTIVE->isActive());
        $this->assertTrue(WalletStatus::FROZEN->isFrozen());
        $this->assertTrue(WalletStatus::RESTRICTED->isRestricted());
    }

    public function test_inactive_allowed_transitions(): void
    {
        $status = WalletStatus::INACTIVE;

        $this->assertTrue($status->canTransitionTo(WalletStatus::ACTIVE));
        $this->assertTrue($status->canTransitionTo(WalletStatus::CLOSED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::FROZEN));
        $this->assertFalse($status->canTransitionTo(WalletStatus::RESTRICTED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::INACTIVE));

        $allowed = $status->allowedTransitions();
        $this->assertCount(2, $allowed);
        $this->assertContains(WalletStatus::ACTIVE, $allowed);
        $this->assertContains(WalletStatus::CLOSED, $allowed);
    }

    public function test_active_allowed_transitions(): void
    {
        $status = WalletStatus::ACTIVE;

        $this->assertTrue($status->canTransitionTo(WalletStatus::FROZEN));
        $this->assertTrue($status->canTransitionTo(WalletStatus::RESTRICTED));
        $this->assertTrue($status->canTransitionTo(WalletStatus::CLOSED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::ACTIVE));
        $this->assertFalse($status->canTransitionTo(WalletStatus::INACTIVE));

        $allowed = $status->allowedTransitions();
        $this->assertCount(3, $allowed);
        $this->assertContains(WalletStatus::FROZEN, $allowed);
        $this->assertContains(WalletStatus::RESTRICTED, $allowed);
        $this->assertContains(WalletStatus::CLOSED, $allowed);
    }

    public function test_frozen_allowed_transitions(): void
    {
        $status = WalletStatus::FROZEN;

        $this->assertTrue($status->canTransitionTo(WalletStatus::ACTIVE));
        $this->assertTrue($status->canTransitionTo(WalletStatus::CLOSED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::FROZEN));
        $this->assertFalse($status->canTransitionTo(WalletStatus::RESTRICTED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::INACTIVE));

        $allowed = $status->allowedTransitions();
        $this->assertCount(2, $allowed);
        $this->assertContains(WalletStatus::ACTIVE, $allowed);
        $this->assertContains(WalletStatus::CLOSED, $allowed);
    }

    public function test_restricted_allowed_transitions(): void
    {
        $status = WalletStatus::RESTRICTED;

        $this->assertTrue($status->canTransitionTo(WalletStatus::ACTIVE));
        $this->assertTrue($status->canTransitionTo(WalletStatus::FROZEN));
        $this->assertTrue($status->canTransitionTo(WalletStatus::CLOSED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::RESTRICTED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::INACTIVE));

        $allowed = $status->allowedTransitions();
        $this->assertCount(3, $allowed);
        $this->assertContains(WalletStatus::ACTIVE, $allowed);
        $this->assertContains(WalletStatus::FROZEN, $allowed);
        $this->assertContains(WalletStatus::CLOSED, $allowed);
    }

    public function test_closed_allowed_transitions(): void
    {
        $status = WalletStatus::CLOSED;

        $this->assertFalse($status->canTransitionTo(WalletStatus::ACTIVE));
        $this->assertFalse($status->canTransitionTo(WalletStatus::FROZEN));
        $this->assertFalse($status->canTransitionTo(WalletStatus::RESTRICTED));
        $this->assertFalse($status->canTransitionTo(WalletStatus::INACTIVE));
        $this->assertFalse($status->canTransitionTo(WalletStatus::CLOSED));

        $allowed = $status->allowedTransitions();
        $this->assertCount(0, $allowed);
    }
}
