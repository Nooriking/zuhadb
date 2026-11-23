<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Ai\AiConversationHistoryBuilder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->withoutVite();

    config([
        'ai.enabled' => true,
        'ai.openai.api_key' => 'test-key-not-real',
        'ai.openai.base_url' => 'https://api.openai.test/v1',
        'ai.openai.model' => 'gpt-test',
        'ai.timeout' => 5,
        'ai.max_tokens' => 128,
        'ai.temperature' => 0.1,
        'ai.rate_limit.per_minute' => 20,
        'ai.history.max_messages' => 40,
        'ai.history.max_message_chars' => 8000,
        'ai.history.max_title_chars' => 120,
    ]);
});

function fakeSuccessfulProvider(string $content = 'Hello from assistant'): void
{
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'model' => 'gpt-test',
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => $content],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ], 200),
    ]);
}

test('authenticated user can access ai chat page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ai')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ai/index')
            ->has('aiEnabled')
            ->has('conversations')
            ->has('messages')
            ->where('aiEnabled', true));
});

test('guest cannot access ai chat', function () {
    $this->get('/ai')->assertRedirect();
    $this->getJson('/ai/conversations')->assertUnauthorized();
    $this->postJson('/ai/chat', [
        'conversation_id' => 1,
        'message' => 'Hi',
    ])->assertUnauthorized();
});

test('user can create and list only their conversations', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    AiConversation::factory()->create(['user_id' => $other->id, 'title' => 'Secret']);

    $this->actingAs($owner)
        ->postJson('/ai/conversations', ['title' => 'My chat'])
        ->assertCreated()
        ->assertJsonPath('conversation.title', 'My chat');

    $response = $this->actingAs($owner)->getJson('/ai/conversations');
    $response->assertOk();

    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('My chat')->not->toContain('Secret');
});

test('user can retrieve own conversation messages but not another users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);
    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'Owner only',
    ]);

    $this->actingAs($owner)
        ->getJson('/ai/conversations/'.$conversation->id)
        ->assertOk()
        ->assertJsonPath('messages.0.content', 'Owner only');

    $this->actingAs($intruder)
        ->getJson('/ai/conversations/'.$conversation->id)
        ->assertNotFound();
});

test('user cannot delete another users conversation', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->deleteJson('/ai/conversations/'.$conversation->id)
        ->assertNotFound();

    expect(AiConversation::query()->find($conversation->id))->not->toBeNull();

    $this->actingAs($owner)
        ->deleteJson('/ai/conversations/'.$conversation->id)
        ->assertOk();

    expect(AiConversation::query()->find($conversation->id))->toBeNull();
});

test('empty and oversized messages are rejected', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $max = (int) config('ai.history.max_message_chars', 8000);

    $this->actingAs($user)
        ->postJson('/ai/chat', [
            'conversation_id' => $conversation->id,
            'message' => '   ',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);

    $this->actingAs($user)
        ->postJson('/ai/chat', [
            'conversation_id' => $conversation->id,
            'message' => str_repeat('a', $max + 1),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('chat persists user and assistant messages with mocked provider', function () {
    fakeSuccessfulProvider('Welcome to Zuha AI');

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'title' => null,
    ]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Hello assistant',
    ]);

    $response->assertOk()
        ->assertJsonPath('user_message.content', 'Hello assistant')
        ->assertJsonPath('assistant_message.content', 'Welcome to Zuha AI');

    expect($response->json())->not->toHaveKey('api_key')
        ->and(json_encode($response->json()))->not->toContain('test-key-not-real');

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(2)
        ->and($conversation->fresh()->title)->toBe('Hello assistant');
});

test('provider failure is handled safely without exposing internals', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response(['error' => 'secret boom'], 500),
    ]);

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Hi',
    ]);

    $response->assertStatus(502)
        ->assertJsonPath('code', 'ai_connection')
        ->assertJsonPath('message', 'Unable to connect to the AI provider. Please try again.')
        ->assertJsonMissing(['error' => 'secret boom']);

    // User message remains; assistant not created. No duplicate user rows.
    expect(AiMessage::query()->where('conversation_id', $conversation->id)->where('role', AiMessage::ROLE_USER)->count())->toBe(1)
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->where('role', AiMessage::ROLE_ASSISTANT)->count())->toBe(0);
});

test('invalid api key rejection returns safe configuration message without secrets', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Invalid API Key',
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
            ],
        ], 401),
    ]);

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Hi',
    ]);

    $response->assertStatus(502)
        ->assertJsonPath('code', 'ai_invalid_response')
        ->assertJsonPath('message', 'AI configuration requires administrator attention.');

    expect(json_encode($response->json()))->not->toContain('Invalid API Key')
        ->and(json_encode($response->json()))->not->toContain('test-key-not-real')
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->where('role', AiMessage::ROLE_ASSISTANT)->count())->toBe(0);
});

test('provider network denial returns connection guidance not configuration message', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Access denied. Please check your network settings.',
            ],
        ], 403),
    ]);

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Hi',
    ]);

    $response->assertStatus(502)
        ->assertJsonPath('code', 'ai_connection')
        ->assertJsonPath(
            'message',
            'The AI provider blocked this network request. Check VPN, firewall, proxy, or regional access, then try again.'
        );

    expect(json_encode($response->json()))->not->toContain('AI configuration requires administrator attention.')
        ->and(AiMessage::query()->where('conversation_id', $conversation->id)->where('role', AiMessage::ROLE_ASSISTANT)->count())->toBe(0);
});

test('invalid provider response is handled safely', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => '']]],
        ], 200),
    ]);

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/ai/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Hi',
        ])
        ->assertStatus(502)
        ->assertJsonPath('code', 'ai_invalid_response');
});

test('ai disabled returns safe service unavailable response', function () {
    config(['ai.enabled' => false]);
    fakeSuccessfulProvider();

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->postJson('/ai/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Hi',
        ])
        ->assertStatus(503)
        ->assertJsonPath('code', 'ai_disabled');

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(0);
});

test('ai chat endpoint is rate limited', function () {
    config(['ai.rate_limit.per_minute' => 2]);
    fakeSuccessfulProvider();

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    RateLimiter::clear('ai-chat:'.$user->id);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'One',
    ])->assertOk();

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Two',
    ])->assertOk();

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Three',
    ])->assertStatus(429);
});

test('conversation history builder bounds and isolates messages', function () {
    config(['ai.history.max_messages' => 3, 'ai.history.max_message_chars' => 20]);

    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $other = AiConversation::factory()->create(['user_id' => $user->id]);

    AiMessage::factory()->create([
        'conversation_id' => $other->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'Other conversation leak',
    ]);

    foreach (['m1', 'm2', 'm3', 'm4', 'm5'] as $text) {
        AiMessage::factory()->create([
            'conversation_id' => $conversation->id,
            'role' => AiMessage::ROLE_USER,
            'content' => $text,
        ]);
    }

    AiMessage::factory()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_SYSTEM,
        'content' => 'stored system should be excluded from history roles filter',
    ]);

    $built = app(AiConversationHistoryBuilder::class)->build($conversation);
    $roles = collect($built)->pluck('role');
    $contents = collect($built)->where('role', '!=', 'system')->pluck('content');

    expect($roles->first())->toBe(AiMessage::ROLE_SYSTEM)
        ->and($contents)->toHaveCount(3)
        ->and($contents->values()->all())->toBe(['m3', 'm4', 'm5'])
        ->and($contents->implode(' '))->not->toContain('Other conversation leak')
        ->and($contents->implode(' '))->not->toContain('stored system');
});

test('chat rejects foreign conversation ids', function () {
    fakeSuccessfulProvider();

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($intruder)
        ->postJson('/ai/chat', [
            'conversation_id' => $conversation->id,
            'message' => 'Peek',
        ])
        ->assertNotFound();

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(0);
});

test('ai page does not expose api key in inertia props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ai')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('ai.openai.api_key')
            ->missing('api_key')
            ->has('aiEnabled'));
});
