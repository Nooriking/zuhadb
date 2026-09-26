<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;

class BranchDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $branchId = (int) ($request->user()?->branch_id ?? 0);

        return app(DashboardController::class)->render($request, $branchId > 0 ? $branchId : null);
    }
}
