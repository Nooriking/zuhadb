<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\AiConversationHistoryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('history builder includes configurable system prompt and truncates long content', function () {
    config([
        'ai.system_prompt' => 'ERP assistant rules',
        'ai.history.max_messages' => 10,
        'ai.history.max_message_chars' => 12,
    ]);

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'abcdefghijklmnopqrstuvwxyz',
    ]);

    $built = app(AiConversationHistoryBuilder::class)->build($conversation);

    expect($built[0])->toMatchArray([
        'role' => AiMessage::ROLE_SYSTEM,
        'content' => 'ERP assistan',
    ])->and($built[1]['content'])->toBe('abcdefghijkl');
});
