<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\Concerns\SanitizesInput;
use App\Models\AiMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiMessageRequest extends FormRequest
{
    use SanitizesInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $maxChars = (int) config('ai.history.max_message_chars', 8000);

        return [
            // Ownership is enforced in AiConversationService — never trust this alone.
            'conversation_id' => ['required', 'integer', 'min:1'],
            'role' => ['required', 'string', Rule::in(AiMessage::ROLES)],
            'content' => ['required', 'string', 'max:'.$maxChars],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
