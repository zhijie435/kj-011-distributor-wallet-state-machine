<?php

namespace Tests\Unit;

use App\Enums\WalletStatus;
use App\Enums\WalletTransitionAction;
use Tests\TestCase;

class WalletTransitionActionEnumTest extends TestCase
{
    public function test_all_actions_have_correct_values(): void
    {
        $this->assertEquals('activate', WalletTransitionAction::ACTIVATE->value);
        $this->assertEquals('freeze', WalletTransitionAction::FREEZE->value);
        $this->assertEquals('unfreeze', WalletTransitionAction::UNFREEZE->value);
        $this->assertEquals('restrict', WalletTransitionAction::RESTRICT->value);
        $this->assertEquals('unrestrict', WalletTransitionAction::UNRESTRICT->value);
        $this->assertEquals('freeze_from_restricted', WalletTransitionAction::FREEZE_FROM_RESTRICTED->value);
        $this->assertEquals('close', WalletTransitionAction::CLOSE->value);
    }

    public function test_action_labels_are_correct(): void
    {
        $this->assertEquals('激活', WalletTransitionAction::ACTIVATE->label());
        $this->assertEquals('冻结', WalletTransitionAction::FREEZE->label());
        $this->assertEquals('解冻', WalletTransitionAction::UNFREEZE->label());
        $this->assertEquals('限制', WalletTransitionAction::RESTRICT->label());
        $this->assertEquals('解除限制', WalletTransitionAction::UNRESTRICT->label());
        $this->assertEquals('受限转冻结', WalletTransitionAction::FREEZE_FROM_RESTRICTED->label());
        $this->assertEquals('注销', WalletTransitionAction::CLOSE->label());
    }

    public function test_from_status_returns_correct_status(): void
    {
        $this->assertEquals(WalletStatus::INACTIVE, WalletTransitionAction::ACTIVATE->fromStatus());
        $this->assertEquals(WalletStatus::ACTIVE, WalletTransitionAction::FREEZE->fromStatus());
        $this->assertEquals(WalletStatus::FROZEN, WalletTransitionAction::UNFREEZE->fromStatus());
        $this->assertEquals(WalletStatus::ACTIVE, WalletTransitionAction::RESTRICT->fromStatus());
        $this->assertEquals(WalletStatus::RESTRICTED, WalletTransitionAction::UNRESTRICT->fromStatus());
        $this->assertEquals(WalletStatus::RESTRICTED, WalletTransitionAction::FREEZE_FROM_RESTRICTED->fromStatus());
    }

    public function test_from_status_throws_exception_for_close_action(): void
    {
        $this->expectException(\LogicException::class);

        WalletTransitionAction::CLOSE->fromStatus();
    }

    public function test_to_status_returns_correct_status(): void
    {
        $this->assertEquals(WalletStatus::ACTIVE, WalletTransitionAction::ACTIVATE->toStatus());
        $this->assertEquals(WalletStatus::FROZEN, WalletTransitionAction::FREEZE->toStatus());
        $this->assertEquals(WalletStatus::ACTIVE, WalletTransitionAction::UNFREEZE->toStatus());
        $this->assertEquals(WalletStatus::RESTRICTED, WalletTransitionAction::RESTRICT->toStatus());
        $this->assertEquals(WalletStatus::ACTIVE, WalletTransitionAction::UNRESTRICT->toStatus());
        $this->assertEquals(WalletStatus::FROZEN, WalletTransitionAction::FREEZE_FROM_RESTRICTED->toStatus());
        $this->assertEquals(WalletStatus::CLOSED, WalletTransitionAction::CLOSE->toStatus());
    }
}
