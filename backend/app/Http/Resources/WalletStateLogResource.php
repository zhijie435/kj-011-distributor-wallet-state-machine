<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletStateLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wallet_id' => $this->wallet_id,
            'from_status' => $this->from_status->value,
            'from_status_label' => $this->from_status->label(),
            'from_status_color' => $this->from_status->color(),
            'to_status' => $this->to_status->value,
            'to_status_label' => $this->to_status->label(),
            'to_status_color' => $this->to_status->color(),
            'action' => $this->action->value,
            'action_label' => $this->action->label(),
            'reason' => $this->reason,
            'operator_id' => $this->operator_id,
            'operator_name' => $this->whenLoaded('operator', fn() => $this->operator?->name),
            'context' => $this->context,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
