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
     * ⭐ آیا این شعبه مدیر دارد؟
     */
    public function hasManager(): bool
    {
        return $this->manager_user_id !== null;
    }

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

    /* =========================================================
     |  ⭐ Manager Assignment (Two-way Link)
     |  منطق:
     |  - اگر manager قبلی داشت و در حال تغییر است → آزادش کن
     |  - manager جدید را ست کن
     |  - user جدید را هم به این شعبه وصل کن (branch_id)
     |  - ⛔ فقط branch_manager های معتبر پذیرفته می‌شوند
     ========================================================= */

    public function assignManager(?User $user): void
    {
        if ($user && ! $user->isBranchManager()) {
            throw new \InvalidArgumentException(
                "User [{$user->id}] must have role 'branch_manager' to be assigned as a manager."
            );
        }

        // One user may manage only one branch.
        if ($user) {
            $alreadyManages = static::query()
                ->where('manager_user_id', $user->id)
                ->where('id', '!=', $this->id)
                ->exists();

            if ($alreadyManages) {
                throw new \InvalidArgumentException(
                    'This user is already the manager of another branch. Each branch manager can manage only one branch.'
                );
            }
        }

        $oldManagerId = $this->manager_user_id;

        if ($oldManagerId && $oldManagerId !== $user?->id) {
            User::where('id', $oldManagerId)
                ->where('branch_id', $this->id)
                ->update(['branch_id' => null]);
        }

        // Clear this user from any other branch manager slots (belt-and-suspenders).
        if ($user) {
            static::query()
                ->where('manager_user_id', $user->id)
                ->where('id', '!=', $this->id)
                ->update(['manager_user_id' => null]);
        }

        $this->manager_user_id = $user?->id;
        $this->save();

        if ($user) {
            $user->update(['branch_id' => $this->id]);
        }
    }
}