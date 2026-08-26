<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'members';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'member_number',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'phone_country_code',
        'country',
        'currency',
        'city',
        'address',
        'sponsor_id',
        'parent_id',
        'leg',
        'current_rank_id',
        'status',
        'avatar_path',
        'bio',
        'date_of_birth',
        'national_id',
    ];

    /**
     * The attributes that should be hidden for serialisation.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'national_id',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'full_name',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth'     => 'date',
            'password'          => 'hashed',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_id');
    }

    /** Direct children in the binary tree */
    public function children(): HasMany
    {
        return $this->hasMany(Member::class, 'parent_id');
    }

    /** Members this member personally sponsored */
    public function downline(): HasMany
    {
        return $this->hasMany(Member::class, 'sponsor_id');
    }

    public function currentRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'current_rank_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function getFullNameAttribute(): string
    {
        $name = trim("{$this->first_name} {$this->last_name}");
        return !empty($name) ? $name : ($this->email ?? 'Member');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Generate a unique member number in MN-XXXXXX format.
     */
    public static function generateMemberNumber(): string
    {
        do {
            $number = 'MN-' . strtoupper(substr(md5(uniqid()), 0, 6));
        } while (static::where('member_number', $number)->exists());

        return $number;
    }
}
