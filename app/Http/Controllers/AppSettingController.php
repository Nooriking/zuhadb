<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingController extends Controller
{
    /* ═══════════════════════════════════════════════════════
     |  INDEX — List all settings
     ═══════════════════════════════════════════════════════ */

    public function index(Request $request): Response
    {
        $search = $request->query('search');

        $settings = AppSetting::query()
            ->when($search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            })
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        // ⭐ Check if a setting already exists
        $hasSettings = AppSetting::count() > 0;

        return Inertia::render('app-settings/index', [
            'settings'    => $settings,
            'hasSettings' => $hasSettings,
            'filters'     => [
                'search' => $search,
            ],
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  CREATE — Create form (only if no settings exist)
     ═══════════════════════════════════════════════════════ */

    public function create(): Response|RedirectResponse
    {
        // ⭐ If a setting already exists → redirect
        if (AppSetting::count() > 0) {
            return redirect()
                ->route('app-settings.index')
                ->with('error', 'A setting already exists. Please edit it instead.');
        }

        return Inertia::render('app-settings/create');
    }

    /* ═══════════════════════════════════════════════════════
     |  STORE — Save setting (only if no settings exist)
     ═══════════════════════════════════════════════════════ */

    public function store(Request $request): RedirectResponse
    {
        // ⭐ If one already exists → redirect
        if (AppSetting::count() > 0) {
            return redirect()
                ->route('app-settings.index')
                ->with('error', 'A setting already exists. Creating new settings is not allowed.');
        }

        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'company_name'       => ['nullable', 'string', 'max:255'],
            'company_name_local' => ['nullable', 'string', 'max:255'],
            'logo'               => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],

            'phone'              => ['nullable', 'string', 'max:50'],
            'phone_2'            => ['nullable', 'string', 'max:50'],
            'email'              => ['nullable', 'email', 'max:255'],
            'website'            => ['nullable', 'url', 'max:255'],

            'address'            => ['nullable', 'string', 'max:500'],

            'social_facebook'    => ['nullable', 'string', 'max:255'],
            'social_instagram'   => ['nullable', 'string', 'max:255'],
            'social_whatsapp'    => ['nullable', 'string', 'max:255'],
            'social_telegram'    => ['nullable', 'string', 'max:255'],
        ]);

        // Upload logo
        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('settings/logos', 'public');
        } else {
            unset($validated['logo']);
        }

        AppSetting::create($validated);

        return redirect()
            ->route('app-settings.index')
            ->with('success', 'Settings created successfully.');
    }

    /* ═══════════════════════════════════════════════════════
     |  SHOW — Display setting
     ═══════════════════════════════════════════════════════ */

    public function show(AppSetting $appSetting): Response
    {
        return Inertia::render('app-settings/show', [
            'setting' => $appSetting,
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  EDIT — Edit form
     ═══════════════════════════════════════════════════════ */

    public function edit(AppSetting $appSetting): Response
    {
        return Inertia::render('app-settings/edit', [
            'setting' => $appSetting,
        ]);
    }

    /* ═══════════════════════════════════════════════════════
     |  UPDATE — Save edit
     ═══════════════════════════════════════════════════════ */

    public function update(Request $request, AppSetting $appSetting): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:255'],
            'company_name'       => ['nullable', 'string', 'max:255'],
            'company_name_local' => ['nullable', 'string', 'max:255'],
            'logo'               => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],

            'phone'              => ['nullable', 'string', 'max:50'],
            'phone_2'            => ['nullable', 'string', 'max:50'],
            'email'              => ['nullable', 'email', 'max:255'],
            'website'            => ['nullable', 'url', 'max:255'],

            'address'            => ['nullable', 'string', 'max:500'],

            'social_facebook'    => ['nullable', 'string', 'max:255'],
            'social_instagram'   => ['nullable', 'string', 'max:255'],
            'social_whatsapp'    => ['nullable', 'string', 'max:255'],
            'social_telegram'    => ['nullable', 'string', 'max:255'],
        ]);

        // Upload new logo
        if ($request->hasFile('logo')) {
            // Delete old logo
            if ($appSetting->logo && Storage::disk('public')->exists($appSetting->logo)) {
                Storage::disk('public')->delete($appSetting->logo);
            }

            $validated['logo'] = $request->file('logo')->store('settings/logos', 'public');
        } else {
            unset($validated['logo']);
        }

        $appSetting->update($validated);

        return redirect()
            ->route('app-settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    /* ═══════════════════════════════════════════════════════
     |  DESTROY — Delete setting (only if more than one exists)
     ═══════════════════════════════════════════════════════ */

    public function destroy(AppSetting $appSetting): RedirectResponse
    {
        // ⭐ If only one exists → reject
        if (AppSetting::count() <= 1) {
            return back()->with('error', 'Cannot delete the only setting.');
        }

        // Delete logo
        if ($appSetting->logo && Storage::disk('public')->exists($appSetting->logo)) {
            Storage::disk('public')->delete($appSetting->logo);
        }

        $appSetting->delete();

        return redirect()
            ->route('app-settings.index')
            ->with('success', 'Settings deleted successfully.');
    }
}