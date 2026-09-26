<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    /* ═══════════════════════════════════════════════════════
     |  Company Settings Fields — Definitions
     ═══════════════════════════════════════════════════════ */

    private const COMPANY_FIELDS = [
        // ─── Company Info ───
        'company_name'            => ['label' => 'Company Name',            'type' => 'string',  'rules' => 'required|string|max:255'],
        'company_name_local'      => ['label' => 'Company Name (Local)',    'type' => 'string',  'rules' => 'nullable|string|max:255'],
        'company_slogan'          => ['label' => 'Slogan',                  'type' => 'string',  'rules' => 'nullable|string|max:255'],
        'company_logo'            => ['label' => 'Logo',                    'type' => 'image',   'rules' => 'nullable|image|max:2048'],
        'company_favicon'         => ['label' => 'Favicon',                 'type' => 'image',   'rules' => 'nullable|image|max:512'],

        // ─── Contact ───
        'company_phone'           => ['label' => 'Primary Phone',           'type' => 'string',  'rules' => 'nullable|string|max:50'],
        'company_phone_2'         => ['label' => 'Secondary Phone',         'type' => 'string',  'rules' => 'nullable|string|max:50'],
        'company_email'           => ['label' => 'Email',                   'type' => 'string',  'rules' => 'nullable|email|max:255'],
        'company_website'         => ['label' => 'Website',                 'type' => 'string',  'rules' => 'nullable|url|max:255'],

        // ─── Address ───
        'company_country'         => ['label' => 'Country',                 'type' => 'string',  'rules' => 'nullable|string|max:100'],
        'company_province'        => ['label' => 'Province',                'type' => 'string',  'rules' => 'nullable|string|max:100'],
        'company_district'        => ['label' => 'District',                'type' => 'string',  'rules' => 'nullable|string|max:100'],
        'company_address'         => ['label' => 'Full Address',            'type' => 'text',    'rules' => 'nullable|string|max:500'],
        'company_postal_code'     => ['label' => 'Postal Code',             'type' => 'string',  'rules' => 'nullable|string|max:20'],

        // ─── Business / Tax ───
        'company_tax_number'      => ['label' => 'Tax Number (TIN)',        'type' => 'string',  'rules' => 'nullable|string|max:50'],
        'company_registration_no' => ['label' => 'Registration Number',     'type' => 'string',  'rules' => 'nullable|string|max:50'],

        // ─── Invoice ───
        'invoice_footer_text'     => ['label' => 'Invoice Footer Text',     'type' => 'text',    'rules' => 'nullable|string|max:500'],
        'invoice_show_logo'       => ['label' => 'Show Logo on Invoice',    'type' => 'boolean', 'rules' => 'boolean'],
        'invoice_show_barcode'    => ['label' => 'Show Barcode on Invoice', 'type' => 'boolean', 'rules' => 'boolean'],

        // ─── Social ───
        'social_facebook'         => ['label' => 'Facebook',                'type' => 'string',  'rules' => 'nullable|string|max:255'],
        'social_instagram'        => ['label' => 'Instagram',               'type' => 'string',  'rules' => 'nullable|string|max:255'],
        'social_whatsapp'         => ['label' => 'WhatsApp',                'type' => 'string',  'rules' => 'nullable|string|max:255'],
        'social_telegram'         => ['label' => 'Telegram',                'type' => 'string',  'rules' => 'nullable|string|max:255'],
    ];

    /* ═══════════════════════════════════════════════════════
     |  INDEX — نمایش فرم تنظیمات
     ═══════════════════════════════════════════════════════ */

    public function index(): Response
    {
        $settings = AppSetting::where('group', 'company')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->key => $s->value])
            ->toArray();

        // cast boolean
        foreach (self::COMPANY_FIELDS as $key => $meta) {
            if ($meta['type'] === 'boolean') {
                $settings[$key] = (bool) ($settings[$key] ?? false);
            }
        }

        // URL کامل لوگو و favicon
        $logoUrl = null;
        if (! empty($settings['company_logo'])) {
            $logoUrl = Storage::disk('public')->url($settings['company_logo']);
        }

        $faviconUrl = null;
        if (! empty($settings['company_favicon'])) {
            $faviconUrl = Storage::disk('public')->url($settings['company_favicon']);
        }

        return Inertia::render('app-settings/index', [
            'settings'   => $settings,
            'fields'     => self::COMPANY_FIELDS,
            'logoUrl'    => $logoUrl,
            'faviconUrl' => $faviconUrl,
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  UPDATE — ذخیره تنظیمات
     ═══════════════════════════════════════════════════════ */

    public function update(Request $request): RedirectResponse
    {
        // قوانین اعتبارسنجی
        $rules = [];
        foreach (self::COMPANY_FIELDS as $key => $meta) {
            $rules[$key] = $meta['rules'];
        }

        $validated = $request->validate($rules);

        // ذخیره‌ی تک‌تک فیلدها
        foreach (self::COMPANY_FIELDS as $key => $meta) {
            if ($meta['type'] === 'image') {
                if ($request->hasFile($key)) {
                    // حذف تصویر قبلی
                    $oldPath = AppSetting::get($key);
                    if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }

                    // ذخیره‌ی تصویر جدید
                    $path = $request->file($key)->store('settings', 'public');
                    AppSetting::set($key, $path, 'string', 'company');
                }
            } elseif ($meta['type'] === 'boolean') {
                AppSetting::set($key, (bool) ($validated[$key] ?? false), 'boolean', 'company');
            } else {
                AppSetting::set($key, $validated[$key] ?? null, 'string', 'company');
            }
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}