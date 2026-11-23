<?php

namespace App\Http\Requests\Ai;

use App\Http\Requests\Concerns\SanitizesInput;
use Illuminate\Foundation\Http\FormRequest;

class SendAiChatMessageRequest extends FormRequest
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
            // Ownership is enforced in AiChatService / AiConversationService.
            'conversation_id' => ['required', 'integer', 'min:1'],
            'message' => ['required', 'string', 'max:'.$maxChars],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Please enter a message.',
            'message.max' => 'Message may not exceed :max characters.',
        ];
    }
}
