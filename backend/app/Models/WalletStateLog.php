<?php

namespace App\Models;

use App\Enums\WalletStatus;
use App\Enums\WalletTransitionAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletStateLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id', 'from_status', 'to_status', 'action',
        'operator_id', 'reason', 'context', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => WalletStatus::class,
            'to_status' => WalletStatus::class,
            'action' => WalletTransitionAction::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(DealerWallet::class, 'wallet_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
