<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $brandId = $this->route('brand')->id ?? null;

        return [
            'name'        => ['required', 'string', 'max:255', Rule::unique('brands', 'name')->ignore($brandId)],
            'country'     => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'logo'        => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'status'      => ['required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Brand name is required.',
            'name.unique'   => 'This brand name is already in use.',
            'status.in'     => 'Status must be either active or inactive.',
        ];
    }
}