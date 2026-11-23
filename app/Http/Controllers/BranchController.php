<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */
    public function index(Request $request): Response
    {
        $authUser = $request->user();

        $search = $request->query('search');
        $status = $request->query('status');

        $branches = Branch::query()
            ->with([
                'user:id,name',        // ✅
                'manager:id,name',     // ✅
            ])
            ->withCount('users')
            ->notTrashed()
            ->visibleTo($authUser)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('branches/Index', [
            'branches'    => $branches,
            'filters'     => [
                'search' => $search,
                'status' => $status,
            ],
            'permissions' => [
                'branches_view'       => $authUser->canManageBranches() || (int) $authUser->branch_id > 0,
                'branches_create'     => $authUser->canManageBranches(),
                'branches_update'     => $authUser->canManageBranches(),
                'branches_deactivate' => $authUser->isSuperAdmin(),
                'is_super_admin'      => $authUser->isSuperAdmin(),
                'is_admin'            => $authUser->isAdmin(),
                'is_branch_manager'   => $authUser->isBranchManager(),
            ],
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */
    public function create(Request $request): Response
    {
        if (! $request->user()->canManageBranches()) {
            abort(403, 'You are not allowed to create branches.');
        }

        return Inertia::render('branches/Create', [
            'permissions' => [
                'branches_create' => $request->user()->canManageBranches(),
            ],
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */
    public function store(StoreBranchRequest $request): RedirectResponse
    {
        if (! $request->user()->canManageBranches()) {
            abort(403, 'You are not allowed to create branches.');
        }

        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['trash']   = false;

        $managerId = $data['manager_user_id'] ?? null;
        unset($data['manager_user_id']);

        try {
            $branch = DB::transaction(function () use ($data, $managerId) {
                $branch = Branch::create($data);

                if ($managerId) {
                    $branch->assignManager(User::findOrFail($managerId));
                }

                return $branch;
            });
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['manager_user_id' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('branches.index')   // ✅ fixed
            ->with('success', "Branch \"{$branch->name}\" was created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */
    public function show(Request $request, Branch $branch): Response
    {
        if ($branch->trash) {
            abort(404);
        }

        $this->authorizeVisibility($request->user(), $branch);

        $branch->load([
            'user:id,name,email',      // ✅ creator → user
            'manager:id,name,email',
            'users' => fn ($q) => $q->limit(20),
        ]);

        $salesCount = DB::table('sales')
            ->where('branch_id', $branch->id)
            ->whereNull('deleted_at')
            ->when(Schema::hasColumn('sales', 'trash'), fn ($q) => $q->where('trash', false))
            ->count();

        $purchasesCount = Schema::hasTable('product_batches')
            ? DB::table('product_batches')
                ->where('branch_id', $branch->id)
                ->when(Schema::hasColumn('product_batches', 'trash'), fn ($q) => $q->where('trash', false))
                ->count()
            : 0;

        $expensesTotal = Schema::hasTable('expenses')
            ? (float) DB::table('expenses')
                ->where('branch_id', $branch->id)
                ->whereNull('deleted_at')
                ->when(Schema::hasColumn('expenses', 'trash'), fn ($q) => $q->where('trash', false))
                ->sum('amount_afn')
            : 0.0;

        return Inertia::render('branches/Show', [
            'branch' => $branch,
            'stats'  => [
                'total_users'     => $branch->users()->count(),
                'total_sales'     => $salesCount,
                'total_purchases' => $purchasesCount,
                'total_expenses'  => $expensesTotal,
            ],
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */
   public function edit(Request $request, Branch $branch): Response
{
    if ($branch->trash) {
        abort(404);
    }

    if (! $branch->canBeEditedBy($request->user())) {
        abort(403, 'You are not allowed to edit this branch.');
    }

    // ✅ Added: load current manager
    $branch->load('manager:id,name,email');

    // ✅ Added: list of free Branch Managers + current manager
    $managers = User::query()
        ->where(function ($q) use ($branch) {
            // ⭐ Branch Managers who do not have a branch yet
            $q->where(function ($sub) {
                $sub->where('role', 'branch_manager')
                    ->whereNull('branch_id');
            })
            // ⭐ or the current manager of this branch
            ->orWhere('id', $branch->manager_user_id);
        })
        ->select('id', 'name', 'email')
        ->orderBy('name')
        ->get();

    return Inertia::render('branches/Edit', [
        'branch'      => $branch,
        'managers'    => $managers,   // ✅ added
        'permissions' => [
            'branches_update'     => $request->user()->canManageBranches(),
            'branches_deactivate' => $request->user()->isSuperAdmin(),
            'is_super_admin'      => $request->user()->isSuperAdmin(),
            'is_admin'            => $request->user()->isAdmin(),
            'is_branch_manager'   => $request->user()->isBranchManager(),
        ],
    ]);
}

    /* =========================================================
     |  UPDATE
     ========================================================= */
    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        if (! $branch->canBeEditedBy($request->user())) {
            abort(403, 'You are not allowed to edit this branch.');
        }

        $data = $request->validated();
        $data['user_id'] = Auth::id();

        $managerChanged = array_key_exists('manager_user_id', $data);
        $managerId      = $data['manager_user_id'] ?? null;
        unset($data['manager_user_id']);

        try {
            DB::transaction(function () use ($data, $branch, $managerChanged, $managerId) {
                $branch->update($data);

                if ($managerChanged) {
                    $branch->assignManager($managerId ? User::findOrFail($managerId) : null);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withErrors(['manager_user_id' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('branches.index')   // ✅ fixed
            ->with('success', "Branch \"{$branch->name}\" was updated successfully.");
    }

    /* =========================================================
     |  DESTROY — move to trash
     ========================================================= */
    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        if (! $request->user()->canManageBranches()) {
            abort(403, 'You are not allowed to trash branches.');
        }

        if ($branch->isTrashed()) {
            return back()->withErrors(['trash' => 'This branch is already in trash.']);
        }

        $name = $branch->name;

        $branch->update([
            'trash'   => true,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('branches.index')
            ->with('success', "Branch \"{$name}\" was moved to trash.");
    }

    /* =========================================================
     |  RESTORE
     ========================================================= */
    public function restore(Request $request, int $id): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can restore branches.');
        }

        $branch = Branch::onlyTrashed()->findOrFail($id);

        $branch->update([
            'trash'   => false,
            'user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('branches.index')
            ->with('success', "Branch \"{$branch->name}\" was restored.");
    }

    /* =========================================================
     |  FORCE DELETE — (disabled)
     ========================================================= */
    public function forceDelete(Request $request, int $id): RedirectResponse
    {
        abort(403, 'Branches can never be permanently deleted.');
    }

    /* =========================================================
     |  TRASH LIST
     ========================================================= */
    public function trash(Request $request): Response
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can view trashed branches.');
        }

        $branches = Branch::onlyTrashed()
            ->with([
                'user:id,name',       // ✅ creator → user
                'manager:id,name',
            ])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('branches/Trash', [   // ✅ capital letter like the others
            'branches' => $branches,
        ]);
    }

    /* =========================================================
     |  ASSIGN MANAGER
     ========================================================= */
    public function assignManager(Request $request, Branch $branch): RedirectResponse
    {
        if (! $branch->canBeEditedBy($request->user())) {
            abort(403, 'You are not allowed to manage this branch.');
        }

        $validated = $request->validate([
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ], [
            'manager_user_id.exists' => 'The selected user does not exist.',
        ]);

        try {
            $managerId = $validated['manager_user_id'] ?? null;

            if ($managerId) {
                $manager = User::findOrFail($managerId);
                $branch->assignManager($manager);
                $message = 'Branch manager assigned successfully.';
            } else {
                $branch->assignManager(null);
                $message = 'Branch manager removed successfully.';
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['manager_user_id' => $e->getMessage()]);
        }

        return back()->with('success', $message);
    }

    /* =========================================================
     |  TOGGLE STATUS — Super Admin only
     ========================================================= */
    public function toggleStatus(Request $request, Branch $branch): RedirectResponse
    {
        if ($branch->isTrashed()) {
            return back()->withErrors(['status' => 'Cannot change status of a trashed branch.']);
        }

        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can change branch status.');
        }

        $branch->update([
            'status'  => $branch->status === 'active' ? 'inactive' : 'active',
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', "Branch status changed to {$branch->status}.");
    }

    /* =========================================================
     |  HELPERS
     ========================================================= */

    protected function authorizeVisibility(?User $authUser, Branch $branch): void
    {
        if (! $authUser) {
            abort(401, 'Unauthenticated.');
        }

        if ($authUser->isSuperAdmin() || $authUser->isAdmin()) {
            return;
        }

        if ((int) $authUser->branch_id !== (int) $branch->id) {
            abort(403, 'You are not allowed to view this branch.');
        }
    }
    /* =========================================================
 |  DEACTIVATE — deactivate branch
 |  ⛔ Super Admin only
 ========================================================= */
public function deactivate(Request $request, Branch $branch): RedirectResponse
{
    if ($branch->isTrashed()) {
        return back()->withErrors(['status' => 'Cannot change status of a trashed branch.']);
    }

    if (! $request->user()->isSuperAdmin()) {
        abort(403, 'Only Super Admin can deactivate branches.');
    }

    if ($branch->isInactive()) {
        return back()->withErrors(['status' => 'Branch is already inactive.']);
    }

    $branch->update([
        'status'  => 'inactive',
        'user_id' => Auth::id(),
    ]);

    return back()->with('success', 'Branch deactivated successfully.');
}

/* =========================================================
 |  ACTIVATE — activate branch
 |  ⛔ Super Admin only
 ========================================================= */
public function activate(Request $request, Branch $branch): RedirectResponse
{
    if ($branch->isTrashed()) {
        return back()->withErrors(['status' => 'Cannot change status of a trashed branch.']);
    }

    if (! $request->user()->isSuperAdmin()) {
        abort(403, 'Only Super Admin can activate branches.');
    }

    if ($branch->isActive()) {
        return back()->withErrors(['status' => 'Branch is already active.']);
    }

    $branch->update([
        'status'  => 'active',
        'user_id' => Auth::id(),
    ]);

    return back()->with('success', 'Branch activated successfully.');
}
}