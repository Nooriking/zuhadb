<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenses,
    ) {}

    public function show(): Response|RedirectResponse
    {
        if ($this->licenses->isEnabled() && $this->licenses->isValid()) {
            return redirect()->route('splash');
        }

        return Inertia::render('license/activate', [
            'status' => $this->licenses->status(),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'min:40', 'max:5000'],
        ]);

        try {
            $payload = $this->licenses->activate($data['license_key']);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'license_key' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'License activated for '.($payload['customer'] ?? 'this computer').'.');
    }
}
