<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_base',
        'status',
        'trash',
        'user_id',
    ];

    protected $casts = [
        'is_base'    => 'boolean',
        'trash'      => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /* =========================================================
     |  Relationships
     ========================================================= */

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* =========================================================
     |  Scopes
     ========================================================= */

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->where('trash', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBase($query)
    {
        return $query->where('is_base', true);
    }

    /* =========================================================
     |  Helpers
     ========================================================= */

    public static function getBase(): ?self
    {
        return self::notTrashed()->base()->first();
    }

    public function isBase(): bool
    {
        return $this->is_base === true;
    }
}