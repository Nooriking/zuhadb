<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @deprecated Investor identity is updated via Users module.
 * InvestorController::update redirects and does not use this request.
 */
class UpdateInvestorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [];
    }
}
