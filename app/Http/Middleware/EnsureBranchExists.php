<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchExists
{
    /**
     * Handle an incoming request.
     *
     * Ensures at least one active branch exists before processing the request.
     * If none exists, a default one is created automatically.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ✅ Safety: only run if the branches table exists (skip during migrations)
        if (! Schema::hasTable('branches')) {
            return $next($request);
        }

        // ✅ Cache the check for 1 hour to avoid hitting DB on every request
        if (! cache()->has('branches_verified')) {
            $activeCount = Branch::query()->notTrashed()->count();

            if ($activeCount === 0) {
                Branch::create([
                    'name'   => 'Kabul — Main',
                    'code'   => 'KBL',
                    'status' => 'active',
                    'trash'  => false,
                ]);

                logger('⚠️ No branches found — a default branch was created automatically by EnsureBranchExists middleware.');
            }

            cache()->put('branches_verified', true, now()->addHour());
        }

        return $next($request);
    }
}