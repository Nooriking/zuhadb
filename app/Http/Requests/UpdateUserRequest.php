<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\Investment;
use App\Models\InvestorTransaction;
use App\Models\InvestorWalletBalance;
use App\Models\InvestorWithdrawal;
use App\Models\User;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        $targetUser = $this->route('user');
        $authUser = $this->user();

        if (! $authUser || ! $targetUser) {
            return false;
        }

        // ✅ Super Admin can edit anyone (including themselves)
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->canManageUser($targetUser);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var User|null $user */
            $user = $this->route('user');
            if (! $user instanceof User) {
                return;
            }

            $newRole = $this->input('role');
            $oldRole = $user->role;

            // Block demoting an investor who still has financial ledger / stock exposure.
            if ($oldRole === User::ROLE_INVESTOR && $newRole !== User::ROLE_INVESTOR) {
                if ($this->userHasFinancialData($user)) {
                    $v->errors()->add(
                        'role',
                        'This investor still has active financial data (investments, transactions, batches). '
                        .'Settle accounts first, or do not change the role — only set status to inactive.'
                    );
                }
            }
        });
    }

    /**
     * Active investor financial exposure that would orphan or break reports on role change.
     */
    private function userHasFinancialData(User $user): bool
    {
        $id = (int) $user->id;

        if (Investment::query()
            ->where('investor_id', $id)
            ->where('status', 'active')
            ->exists()) {
            return true;
        }

        if (InvestorTransaction::query()->where('investor_id', $id)->exists()) {
            return true;
        }

        if (InvestorWithdrawal::query()
            ->where('investor_id', $id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists()) {
            return true;
        }

        if (InvestorWalletBalance::query()
            ->where('investor_id', $id)
            ->where(function ($q) {
                $q->where('capital_balance_afn', '>', 0)
                    ->orWhere('profit_balance_afn', '>', 0);
            })
            ->exists()) {
            return true;
        }

        return Investment::query()
            ->where('investor_id', $id)
            ->whereHas('batches', fn ($q) => $q->where('remaining_quantity', '>', 0))
            ->exists();
    }

    public function rules(): array
    {
        /** @var User $authUser */
        $authUser = $this->user();
        $targetUser = $this->route('user');
        $creatableRoles = $authUser->getCreatableRoles();

        // ✅ If Super Admin is editing themselves, include their own role in the list
        if ($authUser->isSuperAdmin()) {
            $creatableRoles[] = User::ROLE_SUPER_ADMIN;
        }

        return [
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
                function ($attribute, $value, $fail) use ($authUser) {
                    if (! $value) {
                        return;
                    }

                    if ($authUser->isBranchManager() && (int) $value !== (int) $authUser->branch_id) {
                        $fail('You can only assign users to your own branch.');
                    }
                },
            ],
            'name' => SafeData::title(true),
            'username' => array_merge(
                SafeData::username(true),
                [Rule::unique('users', 'username')->ignore($targetUser->id)]
            ),
            'email' => array_merge(
                SafeData::email(false),
                [Rule::unique('users', 'email')->ignore($targetUser->id)]
            ),
            'phone' => SafeData::phone(),
            'password' => ['nullable', 'confirmed', SafeData::strongPassword()],
            'role' => ['required', Rule::in($creatableRoles)],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'username.required' => 'Username is required.',
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username may only contain letters, numbers, dots, dashes and underscores.',
            'email.unique' => 'This email is already registered.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.required' => 'Role is required.',
            'role.in' => 'You are not allowed to assign this role.',
            'status.in' => 'Status must be active, inactive, or blocked.',
        ];
    }

    protected function afterSanitize(): void
    {
        if ($this->user()?->isBranchManager()) {
            $this->merge([
                'branch_id' => $this->user()->branch_id,
            ]);
        }
    }
}