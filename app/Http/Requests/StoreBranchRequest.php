<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manager_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('role', User::ROLE_BRANCH_MANAGER)),
                Rule::unique('branches', 'manager_user_id'),
            ],
            'name'            => ['required', 'string', 'max:255'],
            'code'            => ['required', 'string', 'max:50', 'unique:branches,code'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'country'         => ['nullable', 'string', 'max:100'],
            'province'        => ['nullable', 'string', 'max:100'],
            'district'        => ['nullable', 'string', 'max:100'],
            'address'         => ['nullable', 'string'],
            'status'          => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'            => 'This branch code is already in use.',
            'code.required'          => 'Branch code is required.',
            'name.required'          => 'Branch name is required.',
            'manager_user_id.exists' => 'The selected user must be a branch manager.',
            'manager_user_id.unique' => 'This user is already the manager of another branch.',
        ];
    }
}