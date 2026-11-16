<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @deprecated Investor identity is created via Users + investor role.
 * InvestorController::store redirects to users.create and does not use this request.
 */
class StoreInvestorRequest extends FormRequest
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
