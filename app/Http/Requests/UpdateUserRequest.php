<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\User;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        // ✅ Super Admin می‌تواند هر کسی (از جمله خودش) را ویرایش کند
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->canManageUser($targetUser);
    }

    public function rules(): array
    {
        /** @var User $authUser */
        $authUser = $this->user();
        $targetUser = $this->route('user');
        $creatableRoles = $authUser->getCreatableRoles();

        // ✅ اگر Super Admin خودش را ویرایش می‌کند، نقش خودش را هم به لیست اضافه کن
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