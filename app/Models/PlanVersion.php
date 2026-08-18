<?php

namespace App\Models;

use App\Exceptions\PlanVersionTransitionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'country',
        'description',
        'status',
        'effective_from',
        'effective_to',
        'created_by',
        'approved_by',
        'approved_at',
        'activated_by',
        'activated_at',
        'superseded_by',
        'required_allocation_total',
    ];

    protected function casts(): array
    {
        return [
            'effective_from'            => 'date',
            'effective_to'              => 'date',
            'approved_at'               => 'datetime',
            'activated_at'              => 'datetime',
            'required_allocation_total' => 'decimal:4',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function allocationCategories(): HasMany
    {
        return $this->hasMany(AllocationCategory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'approved_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'activated_by');
    }

    public function supersededByPlan(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class, 'superseded_by');
    }

    // -------------------------------------------------------------------------
    // Status Transition Helpers
    // -------------------------------------------------------------------------

    /**
     * Validate that allocation category percentages sum to the required total.
     * Must pass before transitioning from Draft→Approved or Approved→Active.
     *
     * @throws PlanVersionTransitionException
     */
    public function validateAllocationTotal(): void
    {
        $total = $this->allocationCategories()
            ->where('is_active', true)
            ->sum('percentage');

        $required = (float) $this->required_allocation_total;
        $actual   = round((float) $total, 4);

        if (abs($actual - $required) > 0.0001) {
            throw new PlanVersionTransitionException(
                "Allocation percentages sum to {$actual}%, but {$required}% is required. "
                . "Please adjust allocation categories before transitioning."
            );
        }
    }

    public function isDraft(): bool     { return $this->status === 'draft';      }
    public function isApproved(): bool  { return $this->status === 'approved';   }
    public function isActive(): bool    { return $this->status === 'active';     }
    public function isSuperseded(): bool { return $this->status === 'superseded'; }
}
