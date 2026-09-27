<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseIsValid
{
    public function __construct(
        private readonly LicenseService $licenses,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->licenses->isEnabled()) {
            return $next($request);
        }

        if ($this->licenses->isValid()) {
            return $next($request);
        }

        if ($request->routeIs('license.*') || $request->is('up')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This installation is not licensed for this computer.',
                'machine_id' => $this->licenses->machineId(),
            ], 403);
        }

        return redirect()->route('license.show');
    }
}
