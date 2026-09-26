<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $targetUser = $this->route('user');
        return $this->user()?->canManageUser($targetUser) ?? false;
    }

    public function rules(): array
    {
        /** @var User $authUser */
        $authUser       = $this->user();
        $targetUser     = $this->route('user');
        $creatableRoles = $authUser->getCreatableRoles();

        return [
            'branch_id'     => [
                'nullable',
                'exists:branches,id',
                function ($attribute, $value, $fail) use ($authUser) {
                    if (! $value) return;

                    if ($authUser->isBranchManager() && (int) $value !== (int) $authUser->branch_id) {
                        $fail('You can only assign users to your own branch.');
                    }
                },
            ],
            'name'          => ['required', 'string', 'max:255'],
            'username'      => [
                'required', 'string', 'max:100',
                Rule::unique('users', 'username')->ignore($targetUser->id),
            ],
            'email'         => [
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($targetUser->id),
            ],
            'phone'         => ['nullable', 'string', 'max:50'],

            // ⭐ پسورد قوی (اختیاری در ویرایش)
            'password'      => [
                'nullable',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],

            'role'          => [
                'required',
                Rule::in($creatableRoles),
            ],
            'employee_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('users', 'employee_code')->ignore($targetUser->id),
            ],
            'avatar'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'status'        => ['required', 'in:active,inactive,blocked'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Full name is required.',
            'username.required' => 'Username is required.',
            'username.unique'   => 'This username is already taken.',
            'email.unique'      => 'This email is already registered.',
            'password.confirmed'=> 'Password confirmation does not match.',
            'password.min'      => 'Password must be at least 8 characters.',
            'password.letters'  => 'Password must contain at least one letter.',
            'password.mixed'    => 'Password must contain at least one uppercase and one lowercase letter.',
            'password.numbers'  => 'Password must contain at least one number.',
            'password.symbols'  => 'Password must contain at least one special character.',
            'role.required'     => 'Role is required.',
            'role.in'           => 'You are not allowed to assign this role.',
            'status.in'         => 'Status must be active, inactive, or blocked.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->isBranchManager()) {
            $this->merge([
                'branch_id' => $this->user()->branch_id,
            ]);
        }
    }
}