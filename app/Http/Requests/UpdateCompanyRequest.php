<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = $this->route('company')->id ?? null;

        return [
            'name'     => ['required', 'string', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'email'    => ['nullable', 'email', 'max:255', Rule::unique('companies', 'email')->ignore($companyId)],
            'address'  => ['nullable', 'string'],
            'currency' => ['nullable', 'string', 'max:10'],
            'logo'     => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'status'   => ['required', 'in:active,inactive'],
        ];
    }

    /**
     * Custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'email.unique'  => 'This email is already registered.',
            'status.in'     => 'Status must be either active or inactive.',
        ];
    }
}