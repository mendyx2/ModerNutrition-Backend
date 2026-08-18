<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category',
        'image_path',
        'currency',
        'price_cents',
        'pv',
        'cv',
        'available_countries',
        'status',
        'weight_grams',
        'dimensions',
    ];

    protected function casts(): array
    {
        return [
            'pv'                 => 'decimal:4',
            'cv'                 => 'decimal:4',
            'available_countries' => 'array',
        ];
    }

    public function isAvailableIn(string $countryCode): bool
    {
        if ($this->available_countries === null) {
            return true; // global
        }
        return in_array($countryCode, $this->available_countries, true);
    }
}
