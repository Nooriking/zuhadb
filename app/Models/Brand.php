<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;   // ← SoftDeletes حذف شد چون از ستون trash استفاده می‌کنی

    protected $table = 'brands';

    protected $fillable = [
        'name',
        'country',
        'description',
        'logo',
        'status',
        'trash',
        'user_id',
    ];

    protected $casts = [
        'trash'      => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    public function scopeInTrash($query)   // ← نام جدید، بدون تداخل
    {
        return $query->where('trash', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}