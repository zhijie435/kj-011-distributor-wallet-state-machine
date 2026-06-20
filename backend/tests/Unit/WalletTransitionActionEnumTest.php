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

    public function test_activate_from_and_to_status(): void
    {
        $action = WalletTransitionAction::ACTIVATE;
        $this->assertEquals(WalletStatus::INACTIVE, $action->fromStatus());
        $this->assertEquals(WalletStatus::ACTIVE, $action->toStatus());
    }

    public function test_freeze_from_and_to_status(): void
    {
        $action = WalletTransitionAction::FREEZE;
        $this->assertEquals(WalletStatus::ACTIVE, $action->fromStatus());
        $this->assertEquals(WalletStatus::FROZEN, $action->toStatus());
    }

    public function test_unfreeze_from_and_to_status(): void
    {
        $action = WalletTransitionAction::UNFREEZE;
        $this->assertEquals(WalletStatus::FROZEN, $action->fromStatus());
        $this->assertEquals(WalletStatus::ACTIVE, $action->toStatus());
    }

    public function test_restrict_from_and_to_status(): void
    {
        $action = WalletTransitionAction::RESTRICT;
        $this->assertEquals(WalletStatus::ACTIVE, $action->fromStatus());
        $this->assertEquals(WalletStatus::RESTRICTED, $action->toStatus());
    }

    public function test_unrestrict_from_and_to_status(): void
    {
        $action = WalletTransitionAction::UNRESTRICT;
        $this->assertEquals(WalletStatus::RESTRICTED, $action->fromStatus());
        $this->assertEquals(WalletStatus::ACTIVE, $action->toStatus());
    }

    public function test_freeze_from_restricted_from_and_to_status(): void
    {
        $action = WalletTransitionAction::FREEZE_FROM_RESTRICTED;
        $this->assertEquals(WalletStatus::RESTRICTED, $action->fromStatus());
        $this->assertEquals(WalletStatus::FROZEN, $action->toStatus());
    }

    public function test_close_from_and_to_status(): void
    {
        $action = WalletTransitionAction::CLOSE;
        $this->assertEquals(WalletStatus::ACTIVE, $action->fromStatus());
        $this->assertEquals(WalletStatus::CLOSED, $action->toStatus());
    }
}
