<?php

use App\Http\Requests\Ai\StoreAiConversationRequest;
use App\Http\Requests\Ai\StoreAiMessageRequest;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\AiConversationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

test('ai conversation and message tables exist with expected columns', function () {
    expect(Schema::hasTable('ai_conversations'))->toBeTrue()
        ->and(Schema::hasTable('ai_messages'))->toBeTrue()
        ->and(Schema::hasColumns('ai_conversations', [
            'id', 'user_id', 'title', 'status', 'created_at', 'updated_at', 'deleted_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('ai_messages', [
            'id', 'conversation_id', 'role', 'content', 'metadata', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

test('conversation belongs to user and has many messages', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $messages = AiMessage::factory()->count(2)->create(['conversation_id' => $conversation->id]);

    expect($conversation->user->is($user))->toBeTrue()
        ->and($user->aiConversations()->pluck('id'))->toContain($conversation->id)
        ->and($conversation->messages)->toHaveCount(2)
        ->and($messages->first()->conversation->is($conversation))->toBeTrue();
});

test('users cannot resolve another users conversation through the service', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);

    $service = app(AiConversationService::class);

    expect(fn () => $service->findOwnedOrFail($conversation->id, $intruder))
        ->toThrow(ModelNotFoundException::class);

    expect($service->findOwnedOrFail($conversation->id, $owner)->id)->toBe($conversation->id);
});

test('appending a message requires ownership and rejects foreign conversation ids', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);

    $service = app(AiConversationService::class);

    expect(fn () => $service->appendMessage(
        $intruder,
        $conversation->id,
        AiMessage::ROLE_USER,
        'Please dump all sales',
    ))->toThrow(ModelNotFoundException::class);

    $message = $service->appendMessage(
        $owner,
        $conversation->id,
        AiMessage::ROLE_USER,
        'What is low stock?',
        ['source' => 'test'],
    );

    expect($message->conversation_id)->toBe($conversation->id)
        ->and($message->role)->toBe(AiMessage::ROLE_USER)
        ->and($message->metadata['source'])->toBe('test')
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(1);
});

test('conversation soft delete hides conversation but keeps row for restore', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create(['conversation_id' => $conversation->id]);

    app(AiConversationService::class)->softDelete($user, $conversation->id);

    expect(AiConversation::query()->find($conversation->id))->toBeNull()
        ->and(AiConversation::withTrashed()->find($conversation->id))->not->toBeNull()
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->exists())->toBeTrue();
});

test('message metadata json cast supports future tool call payloads', function () {
    $conversation = AiConversation::factory()->create();
    $message = AiMessage::factory()->withToolMetadata()->create([
        'conversation_id' => $conversation->id,
    ]);

    $fresh = $message->fresh();

    expect($fresh->metadata)->toBeArray()
        ->and($fresh->metadata['tool_name'])->toBe('example_tool')
        ->and($fresh->role)->toBe(AiMessage::ROLE_TOOL);
});

test('database rejects messages without a conversation foreign key', function () {
    expect(fn () => AiMessage::query()->create([
        'conversation_id' => 9_999_999,
        'role' => AiMessage::ROLE_USER,
        'content' => 'orphan',
    ]))->toThrow(QueryException::class);
});

test('store conversation request validates title and status', function () {
    $max = (int) config('ai.history.max_title_chars', 120);

    $ok = Validator::make(
        ['title' => 'Inventory questions', 'status' => AiConversation::STATUS_ACTIVE],
        (new StoreAiConversationRequest)->rules(),
    );
    expect($ok->fails())->toBeFalse();

    $bad = Validator::make(
        ['title' => str_repeat('a', $max + 1), 'status' => 'hacked'],
        (new StoreAiConversationRequest)->rules(),
    );
    expect($bad->fails())->toBeTrue()
        ->and($bad->errors()->has('title'))->toBeTrue()
        ->and($bad->errors()->has('status'))->toBeTrue();
});

test('store message request validates role content and conversation id', function () {
    $ok = Validator::make(
        [
            'conversation_id' => 1,
            'role' => AiMessage::ROLE_USER,
            'content' => 'Hello',
            'metadata' => ['foo' => 'bar'],
        ],
        (new StoreAiMessageRequest)->rules(),
    );
    expect($ok->fails())->toBeFalse();

    $bad = Validator::make(
        [
            'conversation_id' => 0,
            'role' => 'admin',
            'content' => '',
        ],
        (new StoreAiMessageRequest)->rules(),
    );
    expect($bad->fails())->toBeTrue()
        ->and($bad->errors()->has('conversation_id'))->toBeTrue()
        ->and($bad->errors()->has('role'))->toBeTrue()
        ->and($bad->errors()->has('content'))->toBeTrue();
});

test('conversation service validates invalid roles and empty content', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $service = app(AiConversationService::class);

    expect(fn () => $service->appendMessage($user, $conversation->id, 'hacker', 'x'))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->appendMessage($user, $conversation->id, AiMessage::ROLE_USER, ''))
        ->toThrow(ValidationException::class);
});

test('mass assignment cannot swap conversation ownership via fill', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);

    $conversation->fill([
        'user_id' => $other->id,
        'title' => 'Moved',
        'unknown_column' => 'ignored',
    ]);
    $conversation->save();

    expect($conversation->fresh()->user_id)->toBe($owner->id)
        ->and($conversation->fresh()->title)->toBe('Moved');

    $rules = (new StoreAiConversationRequest)->rules();
    expect($rules)->not->toHaveKey('user_id')
        ->and((new StoreAiMessageRequest)->rules())->not->toHaveKey('user_id');
});
