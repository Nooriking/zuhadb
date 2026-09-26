<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AppSetting extends Model
{
    use HasFactory;

    protected $table = 'app_settings';

    protected $fillable = [
        // Company Info
        'name',
        'company_name',
        'company_name_local',
        'logo',

        // Contact
        'phone',
        'phone_2',
        'email',
        'website',

        // Address
        'address',

        // Social
        'social_facebook',
        'social_instagram',
        'social_whatsapp',
        'social_telegram',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /* ═══════════════════════════════════════════════════════
     |  Static Helpers
     ═══════════════════════════════════════════════════════ */

    /**
     * ⭐ Get the current settings (only one row)
     */
    public static function current(): ?self
    {
        return self::first();
    }

    public static function bill(): array
    {
        $setting = self::current();

        $phone = collect([$setting?->phone, $setting?->phone_2])
            ->filter(fn ($value) => filled($value))
            ->implode(' / ');

        return [
            'name' => $setting?->display_name ?? 'ZUHA PERFUME',
            'phone' => $phone,
            'address' => $setting?->address ?? '',
            'slogan' => $setting?->company_name_local ?? '',
            'logo' => $setting?->logo_url ?: '/images/logo.png',
        ];
    }

    /**
     * ⭐ Get a single field value
     */
    public static function get(string $field, mixed $default = null): mixed
    {
        $setting = self::current();

        if (! $setting) {
            return $default;
        }

        return $setting->{$field} ?? $default;
    }

    /**
     * ⭐ Set a single field value
     */
    public static function set(string $field, mixed $value): void
    {
        $setting = self::current();

        if (! $setting) {
            $setting = self::create([
                'name' => 'My Company',
            ]);
        }

        $setting->update([$field => $value]);
    }

    /* ═══════════════════════════════════════════════════════
     |  Accessors
     ═══════════════════════════════════════════════════════ */

    /**
     * ⭐ Full logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->logo)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo);
    }

    /**
     * ⭐ Display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company_name
            ?? $this->name
            ?? 'Company';
    }

    /**
     * ⭐ WhatsApp link
     */
    public function getWhatsappLinkAttribute(): ?string
    {
        if (! $this->social_whatsapp) {
            return null;
        }

        $number = preg_replace('/\D/', '', $this->social_whatsapp);

        return $number ? "https://wa.me/{$number}" : null;
    }
}