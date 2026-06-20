<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealerWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_no' => $this->wallet_no,
            'distributor_id' => $this->distributor_id,
            'distributor_name' => $this->whenLoaded('distributor', fn() => $this->distributor?->name),
            'distributor' => new DistributorResource($this->whenLoaded('distributor')),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'balance' => (float) $this->balance,
            'frozen_amount' => (float) $this->frozen_amount,
            'available_balance' => $this->getAvailableBalance(),
            'credit_limit' => (float) $this->credit_limit,
            'currency' => $this->currency,
            'freeze_reason' => $this->freeze_reason,
            'restrict_reason' => $this->restrict_reason,
            'close_reason' => $this->close_reason,
            'last_activated_at' => $this->last_activated_at?->toDateTimeString(),
            'last_frozen_at' => $this->last_frozen_at?->toDateTimeString(),
            'last_restricted_at' => $this->last_restricted_at?->toDateTimeString(),
            'closed_at' => $this->closed_at?->toDateTimeString(),
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
            'state_logs' => WalletStateLogResource::collection($this->whenLoaded('stateLogs')),
        ];
    }
}
