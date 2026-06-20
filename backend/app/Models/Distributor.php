<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'company_name', 'type', 'region',
    'contact_person', 'phone', 'email', 'address', 'bank_name',
    'bank_account', 'credit_limit', 'balance', 'status', 'parent_id', 'remark',
])]
class Distributor extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Distributor::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(DealerWallet::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
