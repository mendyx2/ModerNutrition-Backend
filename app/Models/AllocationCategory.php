<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllocationCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_version_id',
        'code',
        'name',
        'description',
        'percentage',
        'wallet_bucket',
        'handler_class',
        'is_member_payable',
        'is_pooled',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage'        => 'decimal:4',
            'is_member_payable' => 'boolean',
            'is_pooled'         => 'boolean',
            'is_active'         => 'boolean',
        ];
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }
}
