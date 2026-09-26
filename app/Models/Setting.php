<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
        'updated_by',
    ];

    protected $casts = [
        'is_public'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const GROUPS = [
        'general'   => 'General',
        'company'   => 'Company',
        'finance'   => 'Finance',
        'inventory' => 'Inventory',
        'sales'     => 'Sales',
        'purchase'  => 'Purchase',
        'system'    => 'System',
    ];

    public const TYPES = [
        'string'  => 'Text',
        'integer' => 'Integer',
        'decimal' => 'Decimal',
        'boolean' => 'Boolean',
        'json'    => 'JSON',
        'text'    => 'Long Text',
        'image'   => 'Image',
    ];

    /* Relationships */

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* Cast value based on type */

    public function getTypedValueAttribute()
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'decimal' => (float) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($this->value, true) ?? [],
            'image'   => $this->value ? Storage::disk('public')->url($this->value) : null,
            default   => $this->value,
        };
    }

    /* =========================================================
     |  Static Helpers — Access & Update
     ========================================================= */

    /**
     * خواندن یک تنظیم.
     */
    public static function get(string $key, $default = null)
    {
        $setting = Cache::rememberForever("setting.{$key}", function () use ($key) {
            return self::where('key', $key)->first();
        });

        if (! $setting) {
            return $default;
        }

        return $setting->typed_value ?? $default;
    }

    /**
     * ذخیره یک تنظیم.
     */
    public static function set(string $key, $value, ?string $type = null): self
    {
        $setting = self::where('key', $key)->first();

        $type = $type ?? $setting?->type ?? 'string';

        // تبدیل value به string
        if ($type === 'json' && is_array($value)) {
            $value = json_encode($value);
        } elseif ($type === 'boolean') {
            $value = $value ? '1' : '0';
        }

        if ($setting) {
            $setting->update([
                'value'      => $value,
                'updated_by' => Auth::id(),
            ]);
        } else {
            $setting = self::create([
                'key'        => $key,
                'value'      => $value,
                'type'       => $type,
                'group'      => 'general',
                'updated_by' => Auth::id(),
            ]);
        }

        Cache::forget("setting.{$key}");

        return $setting;
    }

    /**
     * حذف کش همه تنظیمات.
     */
    public static function clearCache(): void
    {
        $keys = self::pluck('key');
        foreach ($keys as $key) {
            Cache::forget("setting.{$key}");
        }
    }
}