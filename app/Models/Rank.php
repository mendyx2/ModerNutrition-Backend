<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'level',
        'description',
        'badge_icon_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function qualificationRules(): HasMany
    {
        return $this->hasMany(QualificationRule::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'current_rank_id');
    }
}
