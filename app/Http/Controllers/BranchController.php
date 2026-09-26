<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canManageBranches() && ! $authUser->isBranchManager()) {
            abort(403, 'You do not have permission to access branches.');
        }

        $query = Branch::query()
            ->with(['manager:id,name,email', 'user:id,name'])
            ->notTrashed()
            ->visibleTo($authUser)
            ->latest();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $branches = $query->paginate(10)->withQueryString();

        return Inertia::render('branches/Index', [
            'branches' => $branches,
            'filters'  => [
                'search' => $request->input('search'),
                'status' => $request->input('status'),
            ],
            'permissions' => [
                'branches_view'      => $authUser->canManageBranches() || $authUser->isBranchManager(),
                'branches_create'    => $authUser->canManageBranches(),
                'branches_update'    => $authUser->canManageBranches(),
                'branches_deactivate'=> $authUser->isSuperAdmin(),
                'is_super_admin'     => $authUser->isSuperAdmin(),
                'is_admin'           => $authUser->isAdmin(),
                'is_branch_manager'  => $authUser->isBranchManager(),
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canManageBranches()) {
            abort(403, 'You are not allowed to create branches.');
        }

        return Inertia::render('branches/Create', [
            'managers' => User::where('status', 'active')
                ->whereIn('role', [User::ROLE_BRANCH_MANAGER, User::ROLE_ADMIN])
                ->select('id', 'name', 'email')
                ->get(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canManageBranches()) {
            abort(403, 'You are not allowed to create branches.');
        }

        $data = $request->validated();
        $data['user_id'] = $authUser->id;
        $data['status']  = $data['status'] ?? 'active';

        Branch::create($data);

        return redirect()
            ->route('branches.index')
            ->with('success', "Branch {$data['name']} was created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(Branch $branch): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if ($branch->trash) {
            abort(404);
        }

        if (! $authUser->canManageBranches() && $authUser->branch_id !== $branch->id) {
            abort(403, 'You do not have permission to view this branch.');
        }

        $branch->load([
            'manager:id,name,email',
            'user:id,name,email',
            'users:id,name,username,email,status,branch_id',
        ]);

        return Inertia::render('branches/Show', [
            'branch' => $branch,
            'stats'  => [
                'total_users'     => $branch->users()->count(),
                'total_sales'     => 0,
                'total_purchases' => 0,
                'total_expenses'  => 0,
            ],
            'permissions' => [
                'branches_update'     => $authUser->canManageBranches(),
                'branches_deactivate' => $authUser->isSuperAdmin(),
                'is_super_admin'      => $authUser->isSuperAdmin(),
            ],
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(Branch $branch): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if ($branch->trash) {
            abort(404);
        }

        if (! $authUser->canManageBranches()) {
            abort(403, 'You are not allowed to edit branches.');
        }

        return Inertia::render('branches/Edit', [
            'branch'   => $branch,
            'managers' => User::where('status', 'active')
                ->whereIn('role', [User::ROLE_BRANCH_MANAGER, User::ROLE_ADMIN])
                ->select('id', 'name', 'email')
                ->get(),
            'permissions' => [
                'branches_update'     => $authUser->canManageBranches(),
                'branches_deactivate' => $authUser->isSuperAdmin(),
                'is_super_admin'      => $authUser->isSuperAdmin(),
            ],
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if ($branch->trash) {
            abort(404);
        }

        if (! $authUser->canManageBranches()) {
            abort(403, 'You are not allowed to update branches.');
        }

        $data = $request->validated();
        $data['user_id'] = $authUser->id;

        $branch->update($data);

        return redirect()
            ->route('branches.index')
            ->with('success', "Branch {$branch->name} was updated successfully.");
    }

    /* =========================================================
     |  DEACTIVATE  (⭐ فقط Super Admin)
     ========================================================= */

    public function deactivate(Branch $branch): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        // ⛔ فقط Super Admin
        if (! $authUser->isSuperAdmin()) {
            return back()->with('error', 'Only Super Admin can deactivate branches.');
        }

        if ($branch->trash) {
            return back()->with('error', 'Cannot deactivate a trashed branch.');
        }

        if ($branch->status === 'inactive') {
            return back()->with('error', 'Branch is already inactive.');
        }

        $branch->update([
            'status'  => 'inactive',
            'user_id' => $authUser->id,
        ]);

        return back()->with('success', "Branch \"{$branch->name}\" has been deactivated.");
    }

    /* =========================================================
     |  ACTIVATE  (⭐ فقط Super Admin)
     ========================================================= */

    public function activate(Branch $branch): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        // ⛔ فقط Super Admin
        if (! $authUser->isSuperAdmin()) {
            return back()->with('error', 'Only Super Admin can activate branches.');
        }

        if ($branch->trash) {
            return back()->with('error', 'Cannot activate a trashed branch.');
        }

        if ($branch->status === 'active') {
            return back()->with('error', 'Branch is already active.');
        }

        $branch->update([
            'status'  => 'active',
            'user_id' => $authUser->id,
        ]);

        return back()->with('success', "Branch \"{$branch->name}\" has been activated.");
    }

    /* =========================================================
     |  ⛔ DESTROY / RESTORE / FORCE DELETE / TRASH
     |  به دلایل امنیتی حذف شدند — Branch فقط غیرفعال می‌شود
     ========================================================= */
}