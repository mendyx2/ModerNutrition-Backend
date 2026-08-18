<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'category',
        'asset_type',
        'product_id',
        'dimensions',
        'format',
        'file_size_bytes',
        'duration_seconds',
        'file_path',
        'file_disk',
        'thumbnail_path',
        'headline',
        'body_copy',
        'cta_text',
        'disclaimer',
        'language',
        'target_countries',
        'target_roles',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_countries' => 'array',
            'target_roles'     => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'updated_by');
    }
}
