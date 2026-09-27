<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Support\Validation\SafeData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddSalePaymentRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount_afn' => SafeData::money(true, 0.01),
            'payment_method' => ['required', Rule::in(['cash', 'card', 'bank', 'mobile', 'credit', 'other'])],
            'reference_number' => SafeData::plainText(100),
            'notes' => SafeData::plainText(500),
        ];
    }
}
