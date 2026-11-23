<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\AiConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiConversationRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $maxTitle = (int) config('ai.history.max_title_chars', 120);

        return [
            'title' => [
                'nullable',
                'string',
                'max:'.$maxTitle,
                'regex:/^[^<>\x00-\x08\x0B\x0C\x0E-\x1F]*$/u',
            ],
            'status' => ['sometimes', 'string', Rule::in(AiConversation::STATUSES)],
        ];
    }
}
