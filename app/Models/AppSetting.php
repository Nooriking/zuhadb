<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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

        // Tax / registration
        'tax_number',
        'registration_no',

        // Invoice / receipt
        'invoice_title',
        'invoice_footer_text',
        'thank_you_message',
        'invoice_show_logo',

        // Social
        'social_facebook',
        'social_instagram',
        'social_whatsapp',
        'social_telegram',
    ];

    protected $casts = [
        'invoice_show_logo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'logo_url',
        'display_name',
    ];

    /* ═══════════════════════════════════════════════════════
     |  Static Helpers
     ═══════════════════════════════════════════════════════ */

    /**
     * Get the current settings (single-row company profile).
     */
    public static function current(): ?self
    {
        return Cache::remember('app_settings.current', 300, fn () => self::query()->first());
    }

    /**
     * Forget cached company profile (call after create/update/delete).
     */
    public static function forgetCache(): void
    {
        Cache::forget('app_settings.current');
        Cache::forget('app_settings.bill');
    }

    /**
     * Receipt / bill payload driven by System Settings (App Settings).
     * No hardcoded store logo. Empty optional fields stay empty.
     *
     * @return array{
     *     name: string,
     *     phone: string,
     *     phone_primary: string,
     *     phone_secondary: string,
     *     support_phone: string,
     *     email: string,
     *     website: string,
     *     address: string,
     *     slogan: string,
     *     logo: string|null,
     *     show_logo: bool,
     *     tax_number: string,
     *     registration_no: string,
     *     invoice_title: string,
     *     invoice_footer: string,
     *     thank_you_message: string,
     *     social_facebook: string,
     *     social_instagram: string,
     *     social_whatsapp: string,
     *     social_telegram: string,
     *     manufacturer: string,
     *     exchange_window_days: int,
     *     default_currency: string
     * }
     */
    public static function bill(): array
    {
        return Cache::remember('app_settings.bill', 300, function () {
            $setting = self::current();

            $phonePrimary = filled($setting?->phone) ? (string) $setting->phone : '';
            $phoneSecondary = filled($setting?->phone_2) ? (string) $setting->phone_2 : '';
            $phone = collect([$phonePrimary, $phoneSecondary])
                ->filter()
                ->unique()
                ->implode(' / ');

            $showLogo = $setting?->invoice_show_logo !== false;
            $logo = ($showLogo && $setting?->logo_url) ? $setting->logo_url : null;

            // Local/display slogan for the store only — never surface software-vendor placeholders.
            $slogan = trim((string) ($setting?->company_name_local ?? ''));
            if ($slogan !== '' && preg_match('/tezhoshan/i', $slogan)) {
                $slogan = '';
            }

            return [
                'name' => $setting?->display_name ?? (string) config('app.name', 'Company'),
                'phone' => $phone,
                'phone_primary' => $phonePrimary,
                'phone_secondary' => $phoneSecondary,
                // Software vendor support (not store identity) — kept for license/support screens only.
                'support_phone' => (string) config('business.support_phone', ''),
                'email' => (string) ($setting?->email ?? ''),
                'website' => (string) ($setting?->website ?? ''),
                'address' => (string) ($setting?->address ?? ''),
                'slogan' => $slogan,
                'logo' => $logo,
                'show_logo' => $showLogo,
                'tax_number' => (string) ($setting?->tax_number ?? ''),
                'registration_no' => (string) ($setting?->registration_no ?? ''),
                'invoice_title' => (string) ($setting?->invoice_title ?: 'Invoice'),
                'invoice_footer' => (string) ($setting?->invoice_footer_text ?? ''),
                'thank_you_message' => trim((string) ($setting?->thank_you_message ?? '')),
                'social_facebook' => (string) ($setting?->social_facebook ?? ''),
                'social_instagram' => (string) ($setting?->social_instagram ?? ''),
                'social_whatsapp' => (string) ($setting?->social_whatsapp ?? ''),
                'social_telegram' => (string) ($setting?->social_telegram ?? ''),
                'manufacturer' => (string) config('business.manufacturer', 'Tezhoshan'),
                'exchange_window_days' => (int) config('business.sale_exchange_window_days', 3),
                'default_currency' => Currency::getDefault()?->code
                    ?? (string) config('business.default_currency', 'AFN'),
            ];
        });
    }

    /**
     * Get a single field value.
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
     * Set a single field value.
     */
    public static function set(string $field, mixed $value): void
    {
        $setting = self::query()->first();

        if (! $setting) {
            $setting = self::create([
                'name' => 'My Company',
            ]);
        }

        $setting->update([$field => $value]);
        self::forgetCache();
    }

    /* ═══════════════════════════════════════════════════════
     |  Accessors
     ═══════════════════════════════════════════════════════ */

    /**
     * Relative logo URL safe for browser print (avoids absolute 127.0.0.1 hosts).
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $path = ltrim((string) $this->logo, '/');

        // Reject path traversal / absolute URLs stored in DB.
        if (str_contains($path, '..') || preg_match('#^(https?:)?//#i', $path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return '/storage/'.$path;
    }

    /**
     * Display name for receipts and UI.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company_name
            ?: $this->name
            ?: 'Company';
    }

    /**
     * WhatsApp link.
     */
    public function getWhatsappLinkAttribute(): ?string
    {
        if (! $this->social_whatsapp) {
            return null;
        }

        $number = preg_replace('/\D/', '', $this->social_whatsapp);

        return $number ? "https://wa.me/{$number}" : null;
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }
}
