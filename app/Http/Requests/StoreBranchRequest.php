<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'       => ['required', 'exists:companies,id'],
            'manager_user_id'  => ['nullable', 'exists:users,id'],
            'name'             => ['required', 'string', 'max:255'],
            'code'             => ['required', 'string', 'max:50', 'unique:branches,code'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:255'],
            'country'          => ['nullable', 'string', 'max:100'],
            'province'         => ['nullable', 'string', 'max:100'],
            'district'         => ['nullable', 'string', 'max:100'],
            'address'          => ['nullable', 'string'],
            'status'           => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.required' => 'Please select a company.',
            'company_id.exists'   => 'The selected company does not exist.',
            'code.unique'         => 'This branch code is already in use.',
            'code.required'       => 'Branch code is required.',
            'name.required'       => 'Branch name is required.',
        ];
    }
}