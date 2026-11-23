<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\StoreAiConversationRequest;
use App\Services\Ai\AiConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConversationController extends Controller
{
    public function __construct(
        private readonly AiConversationService $conversations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->conversations->listForUser($request->user());

        return response()->json([
            'data' => collect($page->items())->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'created_at' => $c->created_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(StoreAiConversationRequest $request): JsonResponse
    {
        $conversation = $this->conversations->create(
            $request->user(),
            $request->validated('title'),
        );

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'status' => $conversation->status,
                'created_at' => $conversation->created_at?->toIso8601String(),
                'updated_at' => $conversation->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function show(Request $request, int $conversation): JsonResponse
    {
        try {
            $payload = $this->conversations->getOwnedWithMessages($request->user(), $conversation);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $c = $payload['conversation'];

        return response()->json([
            'conversation' => [
                'id' => $c->id,
                'title' => $c->title,
                'status' => $c->status,
                'created_at' => $c->created_at?->toIso8601String(),
                'updated_at' => $c->updated_at?->toIso8601String(),
            ],
            'messages' => $payload['messages']->map(fn ($m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function destroy(Request $request, int $conversation): JsonResponse
    {
        try {
            $this->conversations->softDelete($request->user(), $conversation);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        return response()->json(['message' => 'Conversation deleted.']);
    }
}
