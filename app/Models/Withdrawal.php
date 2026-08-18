<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdrawal_number',
        'member_id',
        'wallet_bucket',
        'currency',
        'country',
        'amount',
        'requester_id',
        'approver_id',
        'status',
        'rejection_reason',
        'requested_at',
        'reviewed_at',
        'processed_at',
        'payment_method',
        'payment_details',
        'payment_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'          => 'decimal:8',
            'requested_at'    => 'datetime',
            'reviewed_at'     => 'datetime',
            'processed_at'    => 'datetime',
            'payment_details' => 'encrypted:array',   // encrypted at rest
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'approver_id');
    }

    public static function generateWithdrawalNumber(): string
    {
        do {
            $number = 'WD-' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (static::where('withdrawal_number', $number)->exists());
        return $number;
    }
}
