<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'allocation_category_id',
        'wallet_bucket',
        'currency',
        'country',
        'total_earned',
        'total_withdrawn',
        'total_reversed',
        'available_balance',
        'pending_withdrawal',
        'last_ledger_entry_at',
    ];

    protected function casts(): array
    {
        return [
            'total_earned'         => 'decimal:8',
            'total_withdrawn'      => 'decimal:8',
            'total_reversed'       => 'decimal:8',
            'available_balance'    => 'decimal:8',
            'pending_withdrawal'   => 'decimal:8',
            'last_ledger_entry_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function allocationCategory(): BelongsTo
    {
        return $this->belongsTo(AllocationCategory::class);
    }
}
