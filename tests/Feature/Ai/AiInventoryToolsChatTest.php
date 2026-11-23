<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Inventory;
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

test('chat can call get_product_stock and answer from real inventory', function () {
    $brand = Brand::factory()->create(['name' => 'Dior']);
    $branch = Branch::factory()->create(['name' => 'Kabul']);
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Sauvage',
        'trash' => false,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'DS-001',
        'trash' => false,
    ]);
    ProductBarcode::factory()->create([
        'variant_id' => $variant->id,
        'barcode' => '5555666677778',
        'is_primary' => true,
        'status' => 'active',
    ]);
    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'available_quantity' => 7,
        'quantity' => 7,
        'average_cost_afn' => 8888,
        'trash' => false,
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
                            'id' => 'call_stock_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_product_stock',
                                'arguments' => json_encode(['query' => 'DS-001']),
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
                        'content' => 'Sauvage (DS-001) has 7 units in Kabul.',
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
        'message' => 'How much stock for DS-001?',
    ]);

    $response->assertOk()
        ->assertJsonPath('assistant_message.content', 'Sauvage (DS-001) has 7 units in Kabul.');

    $sawStockToolResult = false;
    Http::assertSent(function ($request) use (&$sawStockToolResult) {
        foreach ($request->data()['messages'] ?? [] as $message) {
            $content = (string) ($message['content'] ?? '');
            if (($message['role'] ?? null) === 'tool' && str_contains($content, 'DS-001') && str_contains($content, '7')) {
                expect($content)->not->toContain('8888');
                $sawStockToolResult = true;

                return true;
            }
        }

        return false;
    });

    expect($sawStockToolResult)->toBeTrue();
});

test('chat can call list_low_stock with threshold grounded results', function () {
    $branch = Branch::factory()->create(['name' => 'Herat']);
    $brand = Brand::factory()->create(['name' => 'Lattafa']);
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Asad',
        'trash' => false,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'ASAD-1',
        'trash' => false,
    ]);
    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'available_quantity' => 1,
        'quantity' => 1,
        'trash' => false,
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
                            'id' => 'call_low_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'list_low_stock',
                                'arguments' => json_encode(['limit' => 10]),
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
                        'content' => 'Asad is low stock with 1 unit left in Herat.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'What is low on stock?',
    ])->assertOk()
        ->assertJsonPath('assistant_message.content', 'Asad is low stock with 1 unit left in Herat.');

    $sawLow = false;
    Http::assertSent(function ($request) use (&$sawLow) {
        foreach ($request->data()['messages'] ?? [] as $message) {
            $content = (string) ($message['content'] ?? '');
            if (($message['role'] ?? null) === 'tool' && str_contains($content, 'ASAD-1') && str_contains($content, 'low_stock')) {
                $sawLow = true;

                return true;
            }
        }

        return false;
    });

    expect($sawLow)->toBeTrue();
});

test('chat still supports search_products alongside inventory tools', function () {
    $brand = Brand::factory()->create(['name' => 'Chanel']);
    Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Bleu de Chanel',
        'trash' => false,
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
                            'id' => 'call_search_keep',
                            'type' => 'function',
                            'function' => [
                                'name' => 'search_products',
                                'arguments' => json_encode(['query' => 'Bleu']),
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
                        'content' => 'Found Bleu de Chanel in the catalog.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Do we carry Bleu?',
    ])->assertOk()
        ->assertJsonPath('assistant_message.content', 'Found Bleu de Chanel in the catalog.');
});

test('investor inventory tool call is unauthorized and returned to the model', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_inv_denied',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_product_stock',
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
                        'content' => 'You do not have permission to view inventory.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_INVESTOR]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'Check stock for Oud',
    ])->assertOk();

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

test('invalid inventory tool arguments are rejected', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::sequence()
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_bad_args',
                            'type' => 'function',
                            'function' => [
                                'name' => 'list_low_stock',
                                'arguments' => json_encode(['limit' => 999]),
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
                        'content' => 'I could not run that with those arguments.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/ai/chat', [
        'conversation_id' => $conversation->id,
        'message' => 'List low stock with huge limit',
    ])->assertOk();

    $sawInvalid = false;
    Http::assertSent(function ($request) use (&$sawInvalid) {
        foreach ($request->data()['messages'] ?? [] as $message) {
            if (($message['role'] ?? null) === 'tool' && str_contains((string) ($message['content'] ?? ''), 'invalid_arguments')) {
                $sawInvalid = true;

                return true;
            }
        }

        return false;
    });

    expect($sawInvalid)->toBeTrue();
});

test('inventory tool round limit remains bounded', function () {
    config(['ai.max_tool_rounds' => 1]);

    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'model' => 'gpt-test',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_loop_stock',
                        'type' => 'function',
                        'function' => [
                            'name' => 'list_low_stock',
                            'arguments' => '{"limit":5}',
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
        'message' => 'Keep listing low stock',
    ])->assertStatus(502)
        ->assertJsonPath('code', 'ai_invalid_response');

    expect(AiMessage::query()->where('conversation_id', $conversation->id)->where('role', AiMessage::ROLE_ASSISTANT)->count())->toBe(0);
});

test('inventory answers do not leak across conversations', function () {
    $branch = Branch::factory()->create();
    $brand = Brand::factory()->create(['name' => 'SecretBrand']);
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'name' => 'Secret Stock Item',
        'trash' => false,
    ]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'SECRET-1',
        'trash' => false,
    ]);
    Inventory::factory()->create([
        'branch_id' => $branch->id,
        'variant_id' => $variant->id,
        'available_quantity' => 2,
        'quantity' => 2,
        'trash' => false,
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
                            'id' => 'call_a',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_product_stock',
                                'arguments' => json_encode(['query' => 'SECRET-1']),
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
                        'content' => 'Secret item has 2 units.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200)
            ->push([
                'model' => 'gpt-test',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello from a different conversation.',
                    ],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
    ]);

    $userA = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $userB = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $conversationA = AiConversation::factory()->create(['user_id' => $userA->id]);
    $conversationB = AiConversation::factory()->create(['user_id' => $userB->id]);

    $this->actingAs($userA)->postJson('/ai/chat', [
        'conversation_id' => $conversationA->id,
        'message' => 'Stock for SECRET-1?',
    ])->assertOk();

    $responseB = $this->actingAs($userB)->postJson('/ai/chat', [
        'conversation_id' => $conversationB->id,
        'message' => 'Hi',
    ])->assertOk();

    expect($responseB->json('assistant_message.content'))->toBe('Hello from a different conversation.')
        ->and(AiMessage::query()->where('conversation_id', $conversationB->id)->where('content', 'like', '%SECRET%')->count())->toBe(0);
});
