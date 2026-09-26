<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';

    protected $fillable = [
        'manager_user_id',
        'user_id',
        'name',
        'code',
        'phone',
        'email',
        'country',
        'province',
        'district',
        'address',
        'status',
        'trash',
    ];

    protected $casts = [
        'trash'      => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function manager(): BelongsTo { return $this->belongsTo(User::class, 'manager_user_id'); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class, 'user_id'); }
    public function users(): HasMany     { return $this->hasMany(User::class, 'branch_id'); }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeNotTrashed($query) { return $query->where('trash', false); }
    public function scopeOnlyTrashed($query){ return $query->where('trash', true); }
    public function scopeActive($query)     { return $query->where('status', 'active'); }
    public function scopeInactive($query)   { return $query->where('status', 'inactive'); }

    public function scopeVisibleTo($query, ?User $authUser)
    {
        if (! $authUser) return $query->whereRaw('1 = 0');

        if ($authUser->isSuperAdmin() || $authUser->isAdmin()) {
            return $query;
        }

        return $query->where('id', $authUser->branch_id);
    }

    /* =========================================================
     |  ⭐ Helpers — Permission-based
     ========================================================= */

    public function isActive(): bool   { return $this->status === 'active'; }
    public function isInactive(): bool { return $this->status === 'inactive'; }
    public function isTrashed(): bool  { return (bool) $this->trash; }

    /**
     * ⭐ فقط Super Admin و Admin می‌توانند شعبه را ویرایش کنند.
     */
    public function canBeEditedBy(User $authUser): bool
    {
        if ($this->isTrashed()) return false;
        return $authUser->canManageBranches();
    }

    /**
     * ⭐ فقط Super Admin می‌تواند شعبه را غیرفعال/فعال کند.
     */
    public function canBeDeactivatedBy(User $authUser): bool
    {
        if ($this->isTrashed()) return false;
        return $authUser->isSuperAdmin();
    }

    /**
     * ⭐ Branch هرگز قابل حذف نیست — نه نرم، نه سخت.
     */
    public function canBeDeletedBy(User $authUser): bool
    {
        return false;
    }
}