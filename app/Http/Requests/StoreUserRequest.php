<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\User;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user()?->canCreateUsers() ?? false;
    }

    public function rules(): array
    {
        /** @var User $authUser */
        $authUser = $this->user();
        $creatableRoles = $authUser->getCreatableRoles();

        return [
            'branch_id' => [
                Rule::requiredIf(fn () => $authUser->isBranchManager()),
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
            'username' => array_merge(SafeData::username(true), ['unique:users,username']),
            'email' => array_merge(SafeData::email(false), [Rule::unique('users', 'email')]),
            'phone' => SafeData::phone(),
            'password' => ['required', 'confirmed', SafeData::strongPassword()],
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
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.required' => 'Role is required.',
            'role.in' => 'You are not allowed to create a user with this role.',
            'status.in' => 'Status must be active, inactive, or blocked.',
            'branch_id.required' => 'Branch is required for your role.',
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