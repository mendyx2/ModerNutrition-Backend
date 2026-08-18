<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'member_id',
        'country',
        'currency',
        'fx_rate_to_usd',
        'subtotal_cents',
        'discount_cents',
        'total_cents',
        'total_pv',
        'total_cv',
        'status',
        'cv_allocated_at',
        'payment_method',
        'payment_reference',
        'paid_at',
        'shipping_address',
        'tracking_number',
        'shipped_at',
        'delivered_at',
        'processed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cv_allocated_at' => 'datetime',
            'paid_at'         => 'datetime',
            'shipped_at'      => 'datetime',
            'delivered_at'    => 'datetime',
            'fx_rate_to_usd'  => 'decimal:8',
            'total_pv'        => 'decimal:4',
            'total_cv'        => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'processed_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function hasCvAllocated(): bool
    {
        return $this->cv_allocated_at !== null;
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));
        } while (static::where('order_number', $number)->exists());

        return $number;
    }
}
