<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualificationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rank_id',
        'min_personal_pv',
        'min_group_gv',
        'min_left_leg_gv',
        'min_right_leg_gv',
        'min_active_frontline',
        'min_qualified_legs',
        'country',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to'   => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }
}
