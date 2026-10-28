<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'currencies';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'rate_to_default',
        'is_base',
        'status',
        'trash',
        'user_id',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'rate_to_default' => 'decimal:8',
        'is_base' => 'boolean',
        'trash' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'is_default',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeNotTrashed($query)
    {
        return $query->where('trash', false);
    }

    public function scopeOnlyTrashed($query)
    {
        return $query->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)
            ->where('trash', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeBase($query)
    {
        return $query->where('is_base', true);
    }

    public function getIsDefaultAttribute(): bool
    {
        return (bool) $this->is_base;
    }

    public static function tableReady(): bool
    {
        return Schema::hasTable('currencies');
    }

    public static function getBase(): ?self
    {
        if (! self::tableReady()) {
            return null;
        }

        return self::query()->notTrashed()->base()->first();
    }

    public static function getDefault(): ?self
    {
        return self::getBase();
    }

    /**
     * Currency of stored POS amounts (AFN ledger). Independent of which
     * catalog row is currently marked as the default/base.
     */
    public static function ledger(): ?self
    {
        if (! self::tableReady()) {
            return null;
        }

        $code = (string) config('business.default_currency', 'AFN');

        return self::query()->notTrashed()->where('code', $code)->first()
            ?? self::getDefault();
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedPayload(): array
    {
        $default = self::getDefault();
        $ledger = self::ledger();

        return [
            'default' => $default ? self::toShared($default) : null,
            'ledger' => $ledger ? self::toShared($ledger) : [
                'code' => (string) config('business.default_currency', 'AFN'),
                'name' => 'Afghan Afghani',
                'symbol' => '؋',
                'decimal_places' => 2,
                'rate_to_default' => '1',
                'is_default' => true,
            ],
            'rate_definition' => 'One unit of this currency equals rate_to_default units of the default currency.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function toShared(self $currency): array
    {
        return [
            'id' => $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'decimal_places' => (int) $currency->decimal_places,
            'rate_to_default' => (string) $currency->rate_to_default,
            'is_default' => (bool) $currency->is_base,
        ];
    }

    public function isBase(): bool
    {
        return $this->is_base === true;
    }

    public function rateIsConfigured(): bool
    {
        if ($this->is_base) {
            return true;
        }

        return $this->rate_to_default !== null && (float) $this->rate_to_default > 0;
    }

    /**
     * Currencies offered on money fields. Inactive rows are included only when
     * they are already attached to the record being edited.
     *
     * @param  list<int|null>  $includeIds
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function formOptions(array $includeIds = [])
    {
        if (! self::tableReady()) {
            return collect();
        }

        $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds))));

        return self::query()
            ->notTrashed()
            ->where(function ($query) use ($includeIds) {
                $query->where('status', 'active');
                if ($includeIds !== []) {
                    $query->orWhereIn('id', $includeIds);
                }
            })
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->get()
            ->map(fn (self $currency) => array_merge(self::toShared($currency), [
                'status' => $currency->status,
                'rate_configured' => $currency->rateIsConfigured(),
            ]))
            ->values();
    }
}
