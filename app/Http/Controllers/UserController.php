<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /* =========================================================
     |  INDEX
     ========================================================= */

    public function index(Request $request): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canAccessUsers()) {
            abort(403, 'You do not have permission to access users.');
        }

        $search   = $request->query('search');
        $status   = $request->query('status');
        $branchId = $request->query('branch_id');
        $role     = $request->query('role');

        $users = User::query()
            ->with(['branch:id,name,code'])
            ->notTrashed()
            ->manageableBy($authUser)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->when($branchId, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($role, fn ($q, $r) => $q->where('role', $r))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $branches = Branch::query()
            ->notTrashed()
            ->visibleTo($authUser)
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('users/index', [
            'users'    => $users,
            'branches' => $branches,
            'roles'    => $authUser->getCreatableRoleLabels(),
            'filters'  => [
                'search'    => $search,
                'status'    => $status,
                'branch_id' => $branchId,
                'role'      => $role,
            ],
            'permissions' => $this->buildPermissions($authUser),
        ]);
    }

    /* =========================================================
     |  CREATE
     ========================================================= */

    public function create(): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canCreateUsers()) {
            abort(403, 'You are not allowed to create users.');
        }

        $branches = Branch::query()
            ->notTrashed()
            ->where('status', 'active')
            ->visibleTo($authUser)
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('users/create', [
            'branches'        => $branches,
            'roles'           => $authUser->getCreatableRoleLabels(),
            'defaultBranchId' => $authUser->isBranchManager() ? $authUser->branch_id : null,
            'isBranchLocked'  => $authUser->isBranchManager(),
        ]);
    }

    /* =========================================================
     |  STORE
     ========================================================= */

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canCreateUsers()) {
            abort(403, 'You are not allowed to create users.');
        }

        if ($data['role'] === User::ROLE_SUPER_ADMIN) {
            abort(403, 'Creating Super Admin users is forbidden.');
        }

        if (! in_array($data['role'], $authUser->getCreatableRoles(), true)) {
            abort(403, 'You are not allowed to create a user with this role.');
        }

        if ($authUser->isBranchManager()) {
            $data['branch_id'] = $authUser->branch_id;
        }

        if ($data['role'] === User::ROLE_SUPER_ADMIN) {
            $data['branch_id'] = null;
        }

        $data['user_id']  = $authUser->id;
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        if ($user->role === User::ROLE_BRANCH_MANAGER && $user->branch_id) {
            $branch = Branch::find($user->branch_id);
            if ($branch) {
                try {
                    $branch->assignManager($user);
                } catch (\InvalidArgumentException $e) {
                    $user->update(['branch_id' => null]);

                    return back()
                        ->withErrors(['branch_id' => $e->getMessage()])
                        ->withInput();
                }
            }
        }

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$user->name}\" was created successfully.");
    }

    /* =========================================================
     |  SHOW
     ========================================================= */

    public function show(User $user): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if ($user->trash) {
            abort(404);
        }

        // ✅ Super Admin می‌تواند همه را ببیند، بقیه طبق قانون
        if (
            ! $authUser->isSuperAdmin()
            && ! $authUser->canManageUser($user)
            && $authUser->id !== $user->id
        ) {
            abort(403, 'You do not have permission to view this user.');
        }

        $user->load(['branch:id,name,code', 'creator:id,name,email']);

        $stats = [
            'total_sales'     => 0,
            'total_purchases' => 0,
            'total_expenses'  => 0,
        ];

        return Inertia::render('users/show', [
            'user'        => $user,
            'stats'       => $stats,
            'roles'       => User::ROLE_LABELS,
            'permissions' => $this->buildPermissions($authUser, $user),
        ]);
    }

    /* =========================================================
     |  EDIT
     ========================================================= */

    public function edit(User $user): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if ($user->trash) {
            abort(404);
        }

        // ✅ Super Admin می‌تواند هر کاربری (از جمله خودش) را ویرایش کند
        if (! $authUser->isSuperAdmin() && ! $authUser->canManageUser($user)) {
            abort(403, 'You are not allowed to edit this user.');
        }

        $branches = Branch::query()
            ->notTrashed()
            ->where('status', 'active')
            ->visibleTo($authUser)
            ->select('id', 'name', 'code')
            ->get();

        return Inertia::render('users/edit', [
            'user'            => $user,
            'branches'        => $branches,
            'roles'           => $authUser->getCreatableRoleLabels(),
            'isBranchLocked'  => $authUser->isBranchManager(),
            'defaultBranchId' => $authUser->isBranchManager() ? $authUser->branch_id : $user->branch_id,
            'permissions'     => $this->buildPermissions($authUser, $user),
        ]);
    }

    /* =========================================================
     |  UPDATE
     ========================================================= */

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        /** @var User $authUser */
        $authUser = Auth::user();

        // ✅ چک اول: آیا اجازه کلی دارد؟
        if (! $authUser->canUpdateUsers()) {
            abort(403, 'You are not allowed to update users.');
        }

        // ✅ بررسی: آیا در حال ویرایش خودش است؟
        $isEditingSelf = $authUser->id === $user->id;

        if ($isEditingSelf && $authUser->isSuperAdmin()) {
            // 🔒 Super Admin نمی‌تواند role خودش را تغییر دهد
            if (isset($data['role']) && $data['role'] !== User::ROLE_SUPER_ADMIN) {
                return back()->with('error', 'You cannot change your own Super Admin role.');
            }

            // 🔒 Super Admin نمی‌تواند status خودش را تغییر دهد
            if (isset($data['status']) && $data['status'] !== 'active') {
                return back()->with('error', 'You cannot change your own account status.');
            }

            // 🔒 Super Admin به هیچ شعبه‌ای وابسته نیست
            $data['branch_id'] = null;
            $data['role']      = User::ROLE_SUPER_ADMIN;
            $data['status']    = 'active';
        } else {
            // ✅ محافظت‌های معمول برای ویرایش دیگران
            if (($data['role'] ?? null) === User::ROLE_SUPER_ADMIN) {
                abort(403, 'Assigning Super Admin role is forbidden.');
            }

            if (! in_array($data['role'] ?? '', $authUser->getCreatableRoles(), true)) {
                abort(403, 'You are not allowed to assign this role.');
            }

            if ($user->isSuperAdmin() && ! $authUser->isSuperAdmin()) {
                abort(403, 'You cannot edit a Super Admin.');
            }

            if ($authUser->isBranchManager()) {
                $data['branch_id'] = $authUser->branch_id;
            }
        }

        $data['user_id'] = $authUser->id;

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $oldBranchId = $user->branch_id;
        $oldRole     = $user->role;

        $user->update($data);

        if ($oldRole === User::ROLE_BRANCH_MANAGER && $oldBranchId) {
            $newRole     = $user->role;
            $newBranchId = $user->branch_id;

            if ($newRole !== User::ROLE_BRANCH_MANAGER || $newBranchId !== $oldBranchId) {
                Branch::where('id', $oldBranchId)
                    ->where('manager_user_id', $user->id)
                    ->update(['manager_user_id' => null]);
            }
        }

        if ($user->role === User::ROLE_BRANCH_MANAGER && $user->branch_id) {
            $branch = Branch::find($user->branch_id);
            if ($branch && (int) $branch->manager_user_id !== (int) $user->id) {
                try {
                    $branch->assignManager($user);
                } catch (\InvalidArgumentException $e) {
                    return back()
                        ->withErrors(['branch_id' => $e->getMessage()])
                        ->withInput();
                }
            }
        }

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$user->name}\" was updated successfully.");
    }

    /* =========================================================
     |  BLOCK
     ========================================================= */

    public function block(User $user): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin users cannot be blocked.');
        }

        if ($user->id === $authUser->id) {
            return back()->with('error', 'You cannot block your own account.');
        }

        if (! $authUser->canBlockTarget($user)) {
            return back()->with('error', 'You do not have permission to block this user.');
        }

        $user->update([
            'status'  => 'blocked',
            'user_id' => $authUser->id,
        ]);

        return back()->with('success', "User \"{$user->name}\" has been blocked.");
    }

    /* =========================================================
     |  UNBLOCK
     ========================================================= */

    public function unblock(User $user): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin users cannot be unblocked.');
        }

        if ($user->id === $authUser->id) {
            return back()->with('error', 'You cannot unblock your own account.');
        }

        if (! $authUser->canUnblockTarget($user)) {
            return back()->with('error', 'You do not have permission to unblock this user.');
        }

        $user->update([
            'status'  => 'active',
            'user_id' => $authUser->id,
        ]);

        return back()->with('success', "User \"{$user->name}\" has been unblocked.");
    }

    /* =========================================================
     |  TOGGLE STATUS (Deprecated)
     ========================================================= */

    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->status === 'blocked') {
            return $this->unblock($user);
        }

        return $this->block($user);
    }

    /* =========================================================
     |  ⭐ PERMISSIONS BUILDER
     ========================================================= */

    protected function buildPermissions(User $authUser, ?User $targetUser = null): array
    {
        return [
            // Users
            'users_view'         => $authUser->canAccessUsers(),
            'users_create'       => $authUser->canCreateUsers(),
            'users_update'       => $authUser->canUpdateUsers(),
            'users_block'        => $authUser->canBlockUsers(),
            'users_unblock'      => $authUser->canUnblockUsers(),

            // Branches
            'branches_view'      => $authUser->isSuperAdmin() || $authUser->isAdmin() || $authUser->isBranchManager(),
            'branches_create'    => $authUser->canManageBranches(),
            'branches_update'    => $authUser->canManageBranches(),
            'branches_delete'    => false,

            // Meta
            'is_super_admin'     => $authUser->isSuperAdmin(),
            'is_admin'           => $authUser->isAdmin(),
            'is_branch_manager'  => $authUser->isBranchManager(),

            // Helpers
            'can_create_users'    => $authUser->canCreateUsers(),
            'can_manage_branches' => $authUser->canManageBranches(),

            // Target-specific
            'can_manage_target'  => $targetUser ? $authUser->canManageUser($targetUser) : null,
            'can_block_target'   => $targetUser ? $authUser->canBlockTarget($targetUser) : null,
            'can_unblock_target' => $targetUser ? $authUser->canUnblockTarget($targetUser) : null,
        ];
    }
}