<?php

namespace App\Http\Controllers;

use App\Support\ReportsCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $categories = ReportsCatalog::forUser($user);

        $availableCount = 0;
        $plannedCount = 0;

        foreach ($categories as $category) {
            foreach ($category['reports'] as $report) {
                if ($report['status'] === 'available' && $report['accessible']) {
                    $availableCount++;
                } elseif ($report['status'] === 'not_implemented') {
                    $plannedCount++;
                }
            }
        }

        return Inertia::render('reports/index', [
            'categories' => $categories,
            'stats' => [
                'available' => $availableCount,
                'planned' => $plannedCount,
                'categories' => count($categories),
            ],
        ]);
    }
}
