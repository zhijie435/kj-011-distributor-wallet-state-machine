<?php

namespace Tests\Unit;

use App\Enums\WalletTransactionType;
use Tests\TestCase;

class WalletTransactionTypeEnumTest extends TestCase
{
    public function test_all_types_have_correct_values(): void
    {
        $this->assertEquals('recharge', WalletTransactionType::RECHARGE->value);
        $this->assertEquals('withdraw', WalletTransactionType::WITHDRAW->value);
        $this->assertEquals('payment', WalletTransactionType::PAYMENT->value);
        $this->assertEquals('refund', WalletTransactionType::REFUND->value);
        $this->assertEquals('transfer_in', WalletTransactionType::TRANSFER_IN->value);
        $this->assertEquals('transfer_out', WalletTransactionType::TRANSFER_OUT->value);
        $this->assertEquals('fee', WalletTransactionType::FEE->value);
        $this->assertEquals('adjustment', WalletTransactionType::ADJUSTMENT->value);
        $this->assertEquals('freeze', WalletTransactionType::FREEZE->value);
        $this->assertEquals('unfreeze', WalletTransactionType::UNFREEZE->value);
    }

    public function test_type_labels_are_correct(): void
    {
        $this->assertEquals('充值', WalletTransactionType::RECHARGE->label());
        $this->assertEquals('提现', WalletTransactionType::WITHDRAW->label());
        $this->assertEquals('消费', WalletTransactionType::PAYMENT->label());
        $this->assertEquals('退款', WalletTransactionType::REFUND->label());
        $this->assertEquals('转入', WalletTransactionType::TRANSFER_IN->label());
        $this->assertEquals('转出', WalletTransactionType::TRANSFER_OUT->label());
        $this->assertEquals('手续费', WalletTransactionType::FEE->label());
        $this->assertEquals('调整', WalletTransactionType::ADJUSTMENT->label());
        $this->assertEquals('冻结', WalletTransactionType::FREEZE->label());
        $this->assertEquals('解冻', WalletTransactionType::UNFREEZE->label());
    }

    public function test_is_income_returns_true_for_income_types(): void
    {
        $this->assertTrue(WalletTransactionType::RECHARGE->isIncome());
        $this->assertTrue(WalletTransactionType::REFUND->isIncome());
        $this->assertTrue(WalletTransactionType::TRANSFER_IN->isIncome());
        $this->assertTrue(WalletTransactionType::UNFREEZE->isIncome());
    }

    public function test_is_income_returns_false_for_expense_types(): void
    {
        $this->assertFalse(WalletTransactionType::WITHDRAW->isIncome());
        $this->assertFalse(WalletTransactionType::PAYMENT->isIncome());
        $this->assertFalse(WalletTransactionType::TRANSFER_OUT->isIncome());
        $this->assertFalse(WalletTransactionType::FEE->isIncome());
        $this->assertFalse(WalletTransactionType::FREEZE->isIncome());
        $this->assertFalse(WalletTransactionType::ADJUSTMENT->isIncome());
    }

    public function test_is_expense_returns_true_for_expense_types(): void
    {
        $this->assertTrue(WalletTransactionType::WITHDRAW->isExpense());
        $this->assertTrue(WalletTransactionType::PAYMENT->isExpense());
        $this->assertTrue(WalletTransactionType::TRANSFER_OUT->isExpense());
        $this->assertTrue(WalletTransactionType::FEE->isExpense());
        $this->assertTrue(WalletTransactionType::FREEZE->isExpense());
    }

    public function test_is_expense_returns_false_for_income_types(): void
    {
        $this->assertFalse(WalletTransactionType::RECHARGE->isExpense());
        $this->assertFalse(WalletTransactionType::REFUND->isExpense());
        $this->assertFalse(WalletTransactionType::TRANSFER_IN->isExpense());
        $this->assertFalse(WalletTransactionType::UNFREEZE->isExpense());
        $this->assertFalse(WalletTransactionType::ADJUSTMENT->isExpense());
    }

    public function test_affects_balance_returns_true_for_balance_affecting_types(): void
    {
        $this->assertTrue(WalletTransactionType::RECHARGE->affectsBalance());
        $this->assertTrue(WalletTransactionType::WITHDRAW->affectsBalance());
        $this->assertTrue(WalletTransactionType::PAYMENT->affectsBalance());
        $this->assertTrue(WalletTransactionType::REFUND->affectsBalance());
        $this->assertTrue(WalletTransactionType::TRANSFER_IN->affectsBalance());
        $this->assertTrue(WalletTransactionType::TRANSFER_OUT->affectsBalance());
        $this->assertTrue(WalletTransactionType::FEE->affectsBalance());
        $this->assertTrue(WalletTransactionType::ADJUSTMENT->affectsBalance());
    }

    public function test_affects_balance_returns_false_for_freeze_types(): void
    {
        $this->assertFalse(WalletTransactionType::FREEZE->affectsBalance());
        $this->assertFalse(WalletTransactionType::UNFREEZE->affectsBalance());
    }
}
