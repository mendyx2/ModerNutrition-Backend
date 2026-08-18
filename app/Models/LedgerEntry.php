<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LedgerEntry — APPEND ONLY.
 *
 * This model deliberately has no update() or save() after creation guard at the
 * application layer. The migration has no updated_at column.
 * Reversals are new rows with is_reversal=true and reversal_reference pointing
 * back to the original entry ID.
 */
class LedgerEntry extends Model
{
    // No updated_at — append-only table
    public $timestamps = false;

    protected $fillable = [
        'entry_type',
        'reference_type',
        'reference_id',
        'member_id',
        'allocation_category_id',
        'plan_version_id',
        'currency',
        'country',
        'amount',
        'cv_basis',
        'percentage_applied',
        'reversal_reference',
        'is_reversal',
        'reversal_reason',
        'running_balance',
        'description',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'             => 'decimal:8',
            'cv_basis'           => 'decimal:4',
            'percentage_applied' => 'decimal:4',
            'running_balance'    => 'decimal:8',
            'is_reversal'        => 'boolean',
            'created_at'         => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function allocationCategory(): BelongsTo
    {
        return $this->belongsTo(AllocationCategory::class);
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'reversal_reference');
    }

    // -------------------------------------------------------------------------
    // Factory method — prevents accidental direct construction
    // -------------------------------------------------------------------------

    /**
     * Create a new ledger entry (the ONLY approved write path).
     * Using a named constructor makes it impossible to accidentally call update().
     */
    public static function record(array $attributes): static
    {
        $attributes['created_at'] = now();
        return static::create($attributes);
    }

    /**
     * Create a reversal entry referencing an existing entry.
     */
    public static function reverse(LedgerEntry $original, string $reason, int $actorId): static
    {
        return static::record([
            'entry_type'             => 'reversal',
            'reference_type'         => $original->reference_type,
            'reference_id'           => $original->reference_id,
            'member_id'              => $original->member_id,
            'allocation_category_id' => $original->allocation_category_id,
            'plan_version_id'        => $original->plan_version_id,
            'currency'               => $original->currency,
            'country'                => $original->country,
            'amount'                 => bcmul($original->amount, '-1', 8),
            'cv_basis'               => $original->cv_basis,
            'percentage_applied'     => $original->percentage_applied,
            'reversal_reference'     => $original->id,
            'is_reversal'            => true,
            'reversal_reason'        => $reason,
            'description'            => "Reversal of entry #{$original->id}: {$reason}",
        ]);
    }
}
