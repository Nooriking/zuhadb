<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductVariant;
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
        'ai.max_tool_rounds' => 5,
        'ai.rate_limit.per_minute' => 20,
        'ai.history.max_messages' => 40,
        'ai.history.max_message_chars' => 8000,
        'ai.history.max_title_chars' => 120,
    ]);
});

test('chat can search real products via search_products tool round', function () {
    $brand = Brand::factory()->create(['name' => 'Dior']);
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Sauvage',
        'trash' => false,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'DIOR-SAV',
        'trash' => false,
    ]);
    ProductBarcode::factory()->create([
        'variant_id' => $variant->id,
        'barcode' => '1111222233334',
        'is_primary' => true,
    ]);

    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_search_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'search_products',
                                'arguments' => json_encode(['query' => 'Sauvage', 'limit' => 5]),
                            ],
                        ]],
                    ],
                    'finish_reason' => 'tool_calls',
                ]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30],
            ], 200)
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Yes — we have Dior Sauvage (SKU DIOR-SAV) in the catalog.',
                    ],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 12, 'total_tokens' => 52],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Do we have Sauvage?',
    ]);

    $response->assertOk()
        ->assertJsonPath('assistant_message.content', 'Yes — we have Dior Sauvage (SKU DIOR-SAV) in the catalog.');

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->count())->toBe(2)
        ->and($response->json('assistant_message.content'))->toContain('Sauvage');

    Http::assertSentCount(2);

    $secondRequestHadToolResult = false;
    Http::assertSent(function ($request) use (&$secondRequestHadToolResult) {
        $data = $request->data();
        if (! isset($data['messages']) || ! is_array($data['messages'])) {
            return false;
        }

        foreach ($data['messages'] as $message) {
            if (($message['role'] ?? null) === 'tool' && str_contains((string) ($message['content'] ?? ''), 'Sauvage')) {
                $secondRequestHadToolResult = true;

                return true;
            }
        }

        return isset($data['tools']);
    });

    expect($secondRequestHadToolResult)->toBeTrue();
});

test('chat still works without tool calls after phase 1c', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'model' => 'gpt-test',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Hello without tools'],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3, 'total_tokens' => 8],
        ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Hi',
    ])->assertOk()
        ->assertJsonPath('assistant_message.content', 'Hello without tools');
});

test('unauthorized product search tool result is returned to the model safely', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_denied',
                            'type' => 'function',
                            'function' => [
                                'name' => 'search_products',
                                'arguments' => '{"query":"Oud"}',
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
                        'content' => 'You do not have permission to search the product catalog.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Find Oud products',
    ])->assertOk()
        ->assertJsonPath('assistant_message.content', 'You do not have permission to search the product catalog.');

    $sawUnauthorized = false;
    Http::assertSent(function ($request) use (&$sawUnauthorized) {
        foreach ($request->data()['messages'] ?? [] as $message) {
            if (($message['role'] ?? null) === 'tool' && str_contains((string) ($message['content'] ?? ''), 'unauthorized')) {
                $sawUnauthorized = true;

                return true;
            }
        }

        return false;
    });

    expect($sawUnauthorized)->toBeTrue();
});

test('unknown tool names requested by the model are not executed', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_evil',
                            'type' => 'function',
                            'function' => [
                                'name' => 'delete_all_products',
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
                        'content' => 'I cannot run that action.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Delete everything',
    ])->assertOk();

    $sawUnknown = false;
    Http::assertSent(function ($request) use (&$sawUnknown) {
        foreach ($request->data()['messages'] ?? [] as $message) {
            if (($message['role'] ?? null) === 'tool' && str_contains((string) ($message['content'] ?? ''), 'unknown_tool')) {
                $sawUnknown = true;

                return true;
            }
        }

        return false;
    });

    expect($sawUnknown)->toBeTrue()
        ->and(Product::query()->count())->toBe(0);
});

test('tool round limit prevents infinite tool loops', function () {
    config(['ai.max_tool_rounds' => 1]);

    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'model' => 'gpt-test',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_loop',
                        'type' => 'function',
                        'function' => [
                            'name' => 'search_products',
                            'arguments' => '{"query":"Oud"}',
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
        ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Find Oud',
    ])->assertStatus(502)
        ->assertJsonPath('code', 'ai_invalid_response');

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->where('role', AiMessage::ROLE_ASSISTANT)->count())->toBe(0);
});
