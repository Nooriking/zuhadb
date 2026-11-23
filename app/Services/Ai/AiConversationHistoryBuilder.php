<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;

/**
 * Builds a bounded, provider-safe message history for chat completions.
 * Does not load ERP business data.
 */
class AiConversationHistoryBuilder
{
    /**
     * Roles allowed in the provider context for Phase 1B (no tool calling).
     */
    private const CONTEXT_ROLES = [
        AiMessage::ROLE_USER,
        AiMessage::ROLE_ASSISTANT,
    ];

    /**
     * @return list<array{role: string, content: string}>
     */
    public function build(AiConversation $conversation, ?string $systemPrompt = null): array
    {
        $maxMessages = max(1, (int) config('ai.history.max_messages', 40));
        $maxChars = max(1, (int) config('ai.history.max_message_chars', 8000));

        $prompt = $systemPrompt ?? (string) config('ai.system_prompt', '');
        $messages = [];

        if ($prompt !== '') {
            $messages[] = [
                'role' => AiMessage::ROLE_SYSTEM,
                'content' => $this->truncate($prompt, $maxChars),
            ];
        }

        $history = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', self::CONTEXT_ROLES)
            ->orderByDesc('id')
            ->limit($maxMessages)
            ->get(['id', 'role', 'content'])
            ->sortBy('id')
            ->values();

        foreach ($history as $row) {
            $content = $this->sanitizeContent((string) $row->content);
            if ($content === '') {
                continue;
            }

            $messages[] = [
                'role' => (string) $row->role,
                'content' => $this->truncate($content, $maxChars),
            ];
        }

        return $messages;
    }

    private function sanitizeContent(string $content): string
    {
        return str_replace("\0", '', $content);
    }

    private function truncate(string $content, int $maxChars): string
    {
        if (mb_strlen($content) <= $maxChars) {
            return $content;
        }

        return mb_substr($content, 0, $maxChars);
    }
}
