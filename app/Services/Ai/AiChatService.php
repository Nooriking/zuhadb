<?php

namespace App\Services\Ai;

use App\Ai\Contracts\AiProvider;
use App\Ai\DTOs\AiCompletionResult;
use App\Ai\DTOs\AiToolCall;
use App\Ai\Exceptions\AiDisabledException;
use App\Ai\Exceptions\AiException;
use App\Ai\Exceptions\AiInvalidResponseException;
use App\Ai\Tools\AiToolCaller;
use App\Ai\Tools\AiToolRegistry;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Orchestrates conversational chat with optional read-only tool rounds.
 * Reuses conversation storage from Phase 1A/1B; tools execute only when the model requests them.
 */
class AiChatService
{
    public function __construct(
        private readonly AiProvider $provider,
        private readonly AiConversationService $conversations,
        private readonly AiConversationHistoryBuilder $historyBuilder,
        private readonly AiToolRegistry $toolRegistry,
        private readonly AiToolCaller $toolCaller,
    ) {}

    /**
     * @return array{
     *     conversation: AiConversation,
     *     user_message: AiMessage,
     *     assistant_message: AiMessage,
     *     result: AiCompletionResult
     * }
     *
     * @throws AiException
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function sendMessage(User $user, int $conversationId, string $content): array
    {
        if (! config('ai.enabled')) {
            throw AiDisabledException::make();
        }

        $content = str_replace("\0", '', trim($content));
        $maxChars = (int) config('ai.history.max_message_chars', 8000);

        if ($content === '' || mb_strlen($content) > $maxChars) {
            throw ValidationException::withMessages([
                'message' => ["Content is required and may not exceed {$maxChars} characters."],
            ]);
        }

        $lockSeconds = max(30, (int) config('ai.timeout', 30) + 15);
        $lock = Cache::lock('ai-chat-conversation:'.$conversationId.':'.$user->id, $lockSeconds);

        if (! $lock->get()) {
            throw new RuntimeException('This conversation is already processing a message. Please wait.');
        }

        try {
            $conversation = $this->conversations->findOwnedOrFail($conversationId, $user);

            if ($conversation->status !== AiConversation::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'conversation_id' => ['This conversation is not active.'],
                ]);
            }

            $userMessage = $this->conversations->appendMessage(
                $user,
                $conversation->id,
                AiMessage::ROLE_USER,
                $content,
            );

            $this->maybeSetTitle($conversation, $content);

            $providerMessages = $this->historyBuilder->build($conversation->fresh());
            $toolSchemas = $this->toolRegistry->toProviderSchemas();
            $options = $toolSchemas === [] ? [] : [
                'tools' => $toolSchemas,
                'tool_choice' => 'auto',
                'parallel_tool_calls' => (bool) config('ai.parallel_tool_calls', false),
            ];

            $maxRounds = max(0, (int) config('ai.max_tool_rounds', 5));
            $result = null;
            $toolsUsed = [];

            for ($round = 0; $round <= $maxRounds; $round++) {
                try {
                    $result = $this->provider->chat($providerMessages, $options);
                } catch (AiException $e) {
                    Log::warning('ai.chat.provider_failed', [
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'exception' => $e::class,
                        'message' => $e->safeMessage(),
                    ]);

                    throw $e;
                }

                if (! $result->hasToolCalls()) {
                    break;
                }

                if ($round >= $maxRounds) {
                    Log::warning('ai.chat.tool_rounds_exhausted', [
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'max_tool_rounds' => $maxRounds,
                    ]);

                    throw AiInvalidResponseException::make(
                        'AI exceeded the maximum number of tool rounds for this message.'
                    );
                }

                $providerMessages[] = $this->assistantToolCallMessage($result);

                foreach ($result->toolCalls as $call) {
                    $execution = $this->toolCaller->execute($user, $call);
                    $toolsUsed[] = $execution->toolName;
                    $providerMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $execution->toolCallId,
                        'name' => $execution->toolName,
                        'content' => $execution->toProviderContent(),
                    ];
                }
            }

            if ($result === null) {
                throw AiInvalidResponseException::make('AI provider returned no result.');
            }

            $assistantContent = trim($result->content);
            if ($assistantContent === '') {
                throw AiInvalidResponseException::make('AI provider returned empty content.');
            }

            $assistantMessage = $this->conversations->appendMessage(
                $user,
                $conversation->id,
                AiMessage::ROLE_ASSISTANT,
                $assistantContent,
                [
                    'model' => $result->model,
                    'finish_reason' => $result->finishReason,
                    'usage' => $result->metadata['usage'] ?? null,
                    'provider' => $this->provider->name(),
                    'tools_used' => array_values(array_unique($toolsUsed)),
                ],
            );

            return [
                'conversation' => $conversation->fresh(),
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
                'result' => $result,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assistantToolCallMessage(AiCompletionResult $result): array
    {
        return [
            'role' => 'assistant',
            'content' => $result->content !== '' ? $result->content : null,
            'tool_calls' => array_map(
                fn (AiToolCall $call) => [
                    'id' => $call->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $call->name,
                        'arguments' => $call->rawArguments !== ''
                            ? $call->rawArguments
                            : json_encode($call->arguments, JSON_UNESCAPED_UNICODE),
                    ],
                ],
                $result->toolCalls,
            ),
        ];
    }

    private function maybeSetTitle(AiConversation $conversation, string $content): void
    {
        if ($conversation->title !== null && $conversation->title !== '') {
            return;
        }

        $maxTitle = (int) config('ai.history.max_title_chars', 120);
        $title = Str::of($content)
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->limit($maxTitle, '')
            ->toString();

        if ($title === '') {
            return;
        }

        DB::transaction(function () use ($conversation, $title) {
            $locked = AiConversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->first();

            if ($locked && ($locked->title === null || $locked->title === '')) {
                $locked->update(['title' => $title]);
                $conversation->title = $title;
            }
        });
    }
}
