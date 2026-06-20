<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'transaction_no' => $this->transaction_no,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'is_income' => $this->type->isIncome(),
            'is_expense' => $this->type->isExpense(),
            'affects_balance' => $this->type->affectsBalance(),
            'amount' => (float) $this->amount,
            'amount_display' => ($this->type->isIncome() ? '+' : '-') . number_format((float) $this->amount, 2),
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'currency' => $this->currency,
            'remark' => $this->remark,
            'operator_id' => $this->operator_id,
            'operator_name' => $this->whenLoaded('operator', fn() => $this->operator?->name),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
