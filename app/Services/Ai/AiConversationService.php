<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ownership-enforced conversation storage for the ERP AI foundation.
 * Does not call AI providers or execute tools.
 */
class AiConversationService
{
    public function create(User $user, ?string $title = null, string $status = AiConversation::STATUS_ACTIVE): AiConversation
    {
        if (! in_array($status, AiConversation::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid conversation status.'],
            ]);
        }

        $maxTitle = (int) config('ai.history.max_title_chars', 120);
        if ($title !== null && mb_strlen($title) > $maxTitle) {
            throw ValidationException::withMessages([
                'title' => ["Title may not be greater than {$maxTitle} characters."],
            ]);
        }

        $conversation = new AiConversation([
            'title' => $title,
            'status' => $status,
        ]);
        $conversation->user_id = $user->id;
        $conversation->save();

        return $conversation;
    }

    /**
     * Resolve a conversation that belongs to the authenticated user.
     * Never use AiConversation::find($id) alone for authorization.
     *
     * @throws ModelNotFoundException
     */
    public function findOwnedOrFail(int $conversationId, User $user): AiConversation
    {
        return AiConversation::query()
            ->ownedBy($user)
            ->whereKey($conversationId)
            ->firstOrFail();
    }

    /**
     * Persist a message only after ownership is verified.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function appendMessage(
        User $user,
        int $conversationId,
        string $role,
        string $content,
        ?array $metadata = null,
    ): AiMessage {
        if (! in_array($role, AiMessage::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ['Invalid message role.'],
            ]);
        }

        $maxChars = (int) config('ai.history.max_message_chars', 8000);
        if ($content === '' || mb_strlen($content) > $maxChars) {
            throw ValidationException::withMessages([
                'content' => ["Content is required and may not exceed {$maxChars} characters."],
            ]);
        }

        // Strip null bytes; avoid storing raw binary. Do not log content.
        $content = str_replace("\0", '', $content);

        return DB::transaction(function () use ($user, $conversationId, $role, $content, $metadata) {
            $conversation = $this->findOwnedOrFail($conversationId, $user);

            $message = $conversation->messages()->create([
                'role' => $role,
                'content' => $content,
                'metadata' => $metadata,
            ]);

            $conversation->touch();

            return $message;
        });
    }

    public function archive(User $user, int $conversationId): AiConversation
    {
        $conversation = $this->findOwnedOrFail($conversationId, $user);
        $conversation->update(['status' => AiConversation::STATUS_ARCHIVED]);

        return $conversation->refresh();
    }

    public function softDelete(User $user, int $conversationId): void
    {
        $this->findOwnedOrFail($conversationId, $user)->delete();
    }

    /**
     * List conversations owned by the user (newest activity first).
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, AiConversation>
     */
    public function listForUser(User $user, ?int $perPage = null)
    {
        $perPage = max(1, min(100, $perPage ?? (int) config('ai.conversations.per_page', 50)));

        return AiConversation::query()
            ->ownedBy($user)
            ->where('status', AiConversation::STATUS_ACTIVE)
            ->latest('updated_at')
            ->paginate($perPage, ['id', 'title', 'status', 'created_at', 'updated_at']);
    }

    /**
     * Load messages for an owned conversation (oldest first, bounded).
     *
     * @return array{conversation: AiConversation, messages: \Illuminate\Support\Collection<int, AiMessage>}
     */
    public function getOwnedWithMessages(User $user, int $conversationId, ?int $limit = null): array
    {
        $conversation = $this->findOwnedOrFail($conversationId, $user);
        $limit = max(1, min(500, $limit ?? (int) config('ai.conversations.messages_limit', 200)));

        $messages = AiMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'conversation_id', 'role', 'content', 'created_at', 'updated_at'])
            ->sortBy('id')
            ->values();

        return [
            'conversation' => $conversation,
            'messages' => $messages,
        ];
    }
}
