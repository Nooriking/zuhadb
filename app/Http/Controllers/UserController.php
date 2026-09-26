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
use Illuminate\Support\Facades\Storage;
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
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('employee_code', 'like', "%{$search}%");
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

        if (! in_array($data['role'], $authUser->getCreatableRoles(), true)) {
            abort(403, 'You are not allowed to create a user with this role.');
        }

        if ($authUser->isBranchManager()) {
            $data['branch_id'] = $authUser->branch_id;
        }

        if ($data['role'] === User::ROLE_SUPER_ADMIN) {
            abort(403, 'Creating Super Admin users is forbidden.');
        }

        $data['user_id']  = $authUser->id;
        $data['password'] = Hash::make($data['password']);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        $user = User::create($data);

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

        if (! $authUser->canManageUser($user) && $authUser->id !== $user->id) {
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

        if (! $authUser->canManageUser($user)) {
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

        if (! $authUser->canUpdateUsers()) {
            abort(403, 'You are not allowed to update users.');
        }

        if (! in_array($data['role'], $authUser->getCreatableRoles(), true)) {
            abort(403, 'You are not allowed to assign this role.');
        }

        if ($authUser->isBranchManager()) {
            $data['branch_id'] = $authUser->branch_id;
        }

        if ($data['role'] === User::ROLE_SUPER_ADMIN) {
            abort(403, 'Assigning Super Admin role is forbidden.');
        }

        if ($user->isSuperAdmin() && ! $authUser->isSuperAdmin()) {
            abort(403, 'You cannot edit a Super Admin.');
        }

        $data['user_id'] = $authUser->id;

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('users/avatars', 'public');
        }

        $user->update($data);

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

        // ⛔ Super Admin هرگز بلاک نمی‌شود — حتی توسط Super Admin دیگر
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin users cannot be blocked.');
        }

        // ⛔ نمی‌توانی خودت را بلاک کنی
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

        // ⛔ Super Admin هرگز آنبلاک نمی‌شود (چون اصلاً نباید بلاک شود)
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin users cannot be unblocked.');
        }

        // ⛔ نمی‌توانی خودت را آنبلاک کنی
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
     |  DESTROY (Trash)
     ========================================================= */

    public function destroy(User $user): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canDeleteUsers()) {
            return back()->with('error', 'Only Super Admin can move users to trash.');
        }

        if ($user->id === $authUser->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin users cannot be deleted.');
        }

        $name = $user->name;

        $user->update([
            'trash'   => true,
            'user_id' => $authUser->id,
        ]);
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$name}\" was moved to trash.");
    }

    /* =========================================================
     |  RESTORE
     ========================================================= */

    public function restore(int $id): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canRestoreUsers()) {
            abort(403, 'Only Super Admin can restore users.');
        }

        $user = User::onlyTrashed()->findOrFail($id);

        $user->update([
            'trash'   => false,
            'user_id' => $authUser->id,
        ]);
        $user->restore();

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$user->name}\" was restored.");
    }

    /* =========================================================
     |  FORCE DELETE
     ========================================================= */

    public function forceDelete(int $id): RedirectResponse
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canForceDeleteUsers()) {
            abort(403, 'Only Super Admin can permanently delete users.');
        }

        $user = User::onlyTrashed()->findOrFail($id);

        if ($user->id === $authUser->id) {
            return back()->with('error', 'You cannot permanently delete your own account.');
        }

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Super Admin users cannot be permanently deleted.');
        }

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $name = $user->name;
        $user->forceDelete();

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$name}\" was permanently deleted.");
    }

    /* =========================================================
     |  TRASH LIST
     ========================================================= */

    public function trash(): Response
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->canViewTrash()) {
            abort(403, 'Only Super Admin can view the trash.');
        }

        $users = User::onlyTrashed()
            ->with(['branch:id,name,code', 'creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(15);

        return Inertia::render('users/trash', [
            'users'       => $users,
            'roles'       => User::ROLE_LABELS,
            'permissions' => $this->buildPermissions($authUser),
        ]);
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
     |  ⭐ MIDDLEWARE-BASED PERMISSIONS BUILDER
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
            'users_delete'       => $authUser->canDeleteUsers(),
            'users_restore'      => $authUser->canRestoreUsers(),
            'users_force_delete' => $authUser->canForceDeleteUsers(),
            'users_trash_view'   => $authUser->canViewTrash(),

            // Branches
            'branches_view'      => $authUser->isSuperAdmin() || $authUser->isAdmin() || $authUser->isBranchManager(),
            'branches_create'    => $authUser->canManageBranches(),
            'branches_update'    => $authUser->canManageBranches(),
            'branches_delete'    => $authUser->isSuperAdmin(),

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