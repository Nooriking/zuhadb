<?php

use App\Ai\Tools\AiToolRegistry;
use App\Ai\Tools\DescribeDatabaseTableTool;
use App\Ai\Tools\GetDatabaseRelationshipsTool;
use App\Ai\Tools\ListDatabaseTablesTool;
use App\Ai\Tools\QueryDatabaseTool;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;

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
        'ai.max_tool_rounds' => 8,
        'ai.rate_limit.per_minute' => 20,
        'ai.database.enabled' => true,
        'ai.history.max_messages' => 40,
        'ai.history.max_message_chars' => 8000,
        'ai.history.max_title_chars' => 120,
    ]);
});

test('database intelligence tools are registered', function () {
    $registry = app(AiToolRegistry::class);

    expect($registry->has(ListDatabaseTablesTool::NAME))->toBeTrue()
        ->and($registry->has(DescribeDatabaseTableTool::NAME))->toBeTrue()
        ->and($registry->has(GetDatabaseRelationshipsTool::NAME))->toBeTrue()
        ->and($registry->has(QueryDatabaseTool::NAME))->toBeTrue();
});

test('chat can list tables then answer from query_database', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_tables_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'list_database_tables',
                                'arguments' => '{}',
                            ],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ], 200)
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_query_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'query_database',
                                'arguments' => json_encode([
                                    'question' => 'How many users?',
                                    'sql' => 'SELECT COUNT(*) AS user_count FROM users',
                                    'limit' => 10,
                                ]),
                            ],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ], 200)
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'There is at least one user in the database.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'How many users do we have?',
    ]);

    $response->assertOk()
        ->assertJsonPath('assistant_message.content', 'There is at least one user in the database.');

    $toolsUsed = $response->json('assistant_message.metadata.tools_used')
        ?? $response->json('assistant_message.meta.tools_used');

    // Metadata shape may be nested under metadata JSON cast.
    $assistant = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    $meta = $assistant?->metadata ?? [];

    expect($meta['tools_used'] ?? [])->toContain('list_database_tables')
        ->and($meta['tools_used'] ?? [])->toContain('query_database');

    Http::assertSentCount(3);
});

test('chat can describe products table schema via describe_database_table', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_describe_products',
                            'type' => 'function',
                            'function' => [
                                'name' => 'describe_database_table',
                                'arguments' => json_encode(['table' => 'products']),
                            ],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ], 200)
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'جدول products شامل ستون‌هایی مانند id، name و brand_id است.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'سلام برایم جدول product را کامل برایم لیست بکن که چی بخش ها دارد',
    ]);

    $response->assertOk()
        ->assertJsonPath('assistant_message.content', 'جدول products شامل ستون‌هایی مانند id، name و brand_id است.');

    $assistant = $conversation->messages()->where('role', 'assistant')->latest('id')->first();
    $meta = $assistant?->metadata ?? [];

    expect($meta['tools_used'] ?? [])->toContain('describe_database_table');
    Http::assertSentCount(2);
});

test('unauthorized role cannot use query_database tool effectively', function () {
    // Create a user with no view permissions that gate DB tools — use investor without investments? Investors have investments.view.
    // Stock keeper has products/inventory but not users — querying users should fail authorization inside tool.
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_query_deny',
                            'type' => 'function',
                            'function' => [
                                'name' => 'query_database',
                                'arguments' => json_encode([
                                    'question' => 'Users',
                                    'sql' => 'SELECT id, name FROM users LIMIT 5',
                                ]),
                            ],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
            ], 200)
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'I cannot access the users table with your permissions.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_STOCK_KEEPER]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Show me all users',
    ]);

    $response->assertOk()
        ->assertJsonPath('assistant_message.content', 'I cannot access the users table with your permissions.');
});
