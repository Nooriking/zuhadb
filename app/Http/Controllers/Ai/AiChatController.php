<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Exceptions\AiConnectionException;
use App\Ai\Exceptions\AiDisabledException;
use App\Ai\Exceptions\AiException;
use App\Ai\Exceptions\AiInvalidResponseException;
use App\Ai\Exceptions\AiRateLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\SendAiChatMessageRequest;
use App\Services\Ai\AiChatService;
use App\Services\Ai\AiConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class AiChatController extends Controller
{
    public function __construct(
        private readonly AiConversationService $conversations,
        private readonly AiChatService $chat,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $conversations = $this->conversations->listForUser($user);

        $activeId = $request->integer('conversation');
        $active = null;
        $messages = [];

        if ($activeId > 0) {
            try {
                $payload = $this->conversations->getOwnedWithMessages($user, $activeId);
                $active = $payload['conversation'];
                $messages = $payload['messages'];
            } catch (ModelNotFoundException) {
                // Invalid / foreign id — render empty selection, do not leak existence.
            }
        }

        return Inertia::render('ai/index', [
            'aiEnabled' => (bool) config('ai.enabled'),
            'maxMessageChars' => (int) config('ai.history.max_message_chars', 8000),
            'conversations' => $conversations,
            'activeConversation' => $active ? [
                'id' => $active->id,
                'title' => $active->title,
                'status' => $active->status,
                'created_at' => $active->created_at?->toIso8601String(),
                'updated_at' => $active->updated_at?->toIso8601String(),
            ] : null,
            'messages' => collect($messages)->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(SendAiChatMessageRequest $request): JsonResponse
    {
        $user = $request->user();
        $conversationId = (int) $request->validated('conversation_id');
        $message = (string) $request->validated('message');

        try {
            $result = $this->chat->sendMessage($user, $conversationId, $message);
        } catch (ModelNotFoundException) {
            return response()->json([
                'message' => 'Conversation not found.',
            ], 404);
        } catch (AiDisabledException $e) {
            return response()->json([
                'message' => $e->safeMessage(),
                'code' => 'ai_disabled',
            ], 503);
        } catch (AiRateLimitException $e) {
            return response()->json([
                'message' => $e->safeMessage(),
                'code' => 'ai_rate_limited',
            ], 429);
        } catch (AiConnectionException $e) {
            return response()->json([
                'message' => $e->safeMessage(),
                'code' => 'ai_connection',
            ], 502);
        } catch (AiInvalidResponseException $e) {
            return response()->json([
                'message' => $e->safeMessage(),
                'code' => 'ai_invalid_response',
            ], 502);
        } catch (AiException $e) {
            return response()->json([
                'message' => $e->safeMessage(),
                'code' => 'ai_error',
            ], 502);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Please wait for the current reply to finish.',
                'code' => 'ai_busy',
            ], 409);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('ai.chat.unexpected', [
                'exception' => $e::class,
                'user_id' => $user?->id,
            ]);

            return response()->json([
                'message' => 'Unable to process the AI request right now.',
                'code' => 'ai_unexpected',
            ], 500);
        }

        $conversation = $result['conversation'];
        $userMessage = $result['user_message'];
        $assistantMessage = $result['assistant_message'];

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'status' => $conversation->status,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ],
            'user_message' => [
                'id' => $userMessage->id,
                'role' => $userMessage->role,
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at?->toIso8601String(),
            ],
            'assistant_message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role,
                'content' => $assistantMessage->content,
                'created_at' => $assistantMessage->created_at?->toIso8601String(),
            ],
        ]);
    }
}
