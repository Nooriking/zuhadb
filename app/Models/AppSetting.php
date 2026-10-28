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

        $supportPhone = filled($setting?->phone)
            ? (string) $setting->phone
            : (string) config('business.support_phone', '0744442863');

        $phone = collect([$supportPhone, $setting?->phone_2])
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->implode(' / ');

        return [
            'name' => $setting?->display_name ?? 'ZUHA PERFUME',
            'phone' => $phone,
            'support_phone' => $supportPhone,
            'manufacturer' => (string) config('business.manufacturer', 'Tezhoshan'),
            'exchange_window_days' => (int) config('business.sale_exchange_window_days', 3),
            'default_currency' => Currency::getDefault()?->code
                ?? (string) config('business.default_currency', 'AFN'),
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