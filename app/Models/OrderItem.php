<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_sku',
        'product_name',
        'currency',
        'quantity',
        'unit_price_cents',
        'line_total_cents',
        'unit_pv',
        'unit_cv',
        'line_pv',
        'line_cv',
    ];

    protected function casts(): array
    {
        return [
            'unit_pv' => 'decimal:4',
            'unit_cv' => 'decimal:4',
            'line_pv' => 'decimal:4',
            'line_cv' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
