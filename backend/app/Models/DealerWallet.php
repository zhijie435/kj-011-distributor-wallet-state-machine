<?php

namespace App\Models;

use App\Enums\WalletStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'distributor_id', 'wallet_no', 'status', 'balance', 'frozen_amount',
    'credit_limit', 'currency', 'last_activated_at', 'last_frozen_at',
    'last_restricted_at', 'closed_at', 'freeze_reason', 'restrict_reason',
    'close_reason', 'metadata',
])]
#[ObservedBy([\App\Observers\DealerWalletObserver::class])]
class DealerWallet extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'frozen_amount' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'status' => WalletStatus::class,
            'last_activated_at' => 'datetime',
            'last_frozen_at' => 'datetime',
            'last_restricted_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    public function stateLogs(): HasMany
    {
        return $this->hasMany(WalletStateLog::class, 'wallet_id');
    }

    public function isActive(): bool
    {
        return $this->status === WalletStatus::ACTIVE;
    }

    public function isFrozen(): bool
    {
        return $this->status === WalletStatus::FROZEN;
    }

    public function isRestricted(): bool
    {
        return $this->status === WalletStatus::RESTRICTED;
    }

    public function isInactive(): bool
    {
        return $this->status === WalletStatus::INACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === WalletStatus::CLOSED;
    }

    public function getAvailableBalance(): float
    {
        return max(0, (float) $this->balance - (float) $this->frozen_amount);
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->getAvailableBalance() >= $amount;
    }

    public function scopeByStatus($query, WalletStatus $status)
    {
        return $query->where('status', $status->value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', WalletStatus::ACTIVE->value);
    }

    public function scopeFrozen($query)
    {
        return $query->where('status', WalletStatus::FROZEN->value);
    }
}
