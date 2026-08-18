<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog — append-only. No updated_at.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_log';

    protected $fillable = [
        'actor_id',
        'actor_email',
        'actor_ip',
        'actor_user_agent',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'metadata',
        'description',
        'country',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata'   => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'actor_id');
    }

    /**
     * Static helper to record an audit event.
     */
    public static function record(
        string $event,
        ?Member $actor,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?string $description = null
    ): static {
        return static::create([
            'actor_id'       => $actor?->id,
            'actor_email'    => $actor?->email,
            'actor_ip'       => request()->ip(),
            'actor_user_agent' => request()->userAgent(),
            'event'          => $event,
            'auditable_type' => $subject ? get_class($subject) : null,
            'auditable_id'   => $subject?->getKey(),
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
            'metadata'       => $metadata ?: null,
            'description'    => $description,
            'country'        => $actor?->country,
            'created_at'     => now(),
        ]);
    }
}
