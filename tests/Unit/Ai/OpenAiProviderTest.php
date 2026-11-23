<?php

use App\Ai\Exceptions\AiConnectionException;
use App\Ai\Exceptions\AiDisabledException;
use App\Ai\Exceptions\AiInvalidResponseException;
use App\Ai\Exceptions\AiRateLimitException;
use App\Ai\Providers\OpenAiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'ai.enabled' => true,
        'ai.openai.api_key' => 'test-key-not-real',
        'ai.openai.base_url' => 'https://api.openai.test/v1',
        'ai.openai.model' => 'gpt-test',
        'ai.timeout' => 5,
        'ai.max_tokens' => 128,
        'ai.temperature' => 0.1,
    ]);
});

test('openai provider returns completion content on success', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'model' => 'gpt-test',
            'choices' => [
                [
                    'message' => ['role' => 'assistant', 'content' => 'Hello from AI'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 4,
                'total_tokens' => 14,
            ],
        ], 200),
    ]);

    $result = app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]);

    expect($result->content)->toBe('Hello from AI')
        ->and($result->model)->toBe('gpt-test')
        ->and($result->finishReason)->toBe('stop')
        ->and($result->metadata['usage']['total_tokens'])->toBe(14);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://api.openai.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key-not-real')
            && ($data['max_completion_tokens'] ?? null) === 128
            && ! array_key_exists('max_tokens', $data)
            && ! str_contains(json_encode($data), 'test-key-not-real');
    });
});

test('openai provider refuses when ai is disabled', function () {
    config(['ai.enabled' => false]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(AiDisabledException::class);
});

test('openai provider maps http 429 to rate limit exception', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response(['error' => 'rate'], 429, [
            'Retry-After' => '12',
        ]),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiRateLimitException::class,
        'AI requests are temporarily limited. Please wait and try again.',
    );
});

test('openai provider maps connection failure safely without logging secrets', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    Log::spy();

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'secret customer phone 0700000000'],
    ]))->toThrow(
        AiConnectionException::class,
        'Unable to connect to the AI provider. Please try again.',
    );

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
        $encoded = json_encode($context);

        return $message === 'ai.provider.connection_error'
            && ! str_contains($encoded, 'test-key-not-real')
            && ! str_contains($encoded, '0700000000')
            && ! str_contains($encoded, 'secret customer phone');
    });
});

test('openai provider maps http 400 to safe invalid request message with sanitized logs', function () {
    Log::spy();

    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Invalid model using key sk-proj-SECRETVALUE1234567890',
                'type' => 'invalid_request_error',
                'code' => 'model_not_found',
            ],
        ], 400, [
            'x-request-id' => 'req_test_400',
        ]),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiInvalidResponseException::class,
        'The AI request could not be processed. Please try again.',
    );

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
        $encoded = json_encode($context);

        return $message === 'ai.provider.http_error'
            && ($context['status'] ?? null) === 400
            && ($context['error_code'] ?? null) === 'model_not_found'
            && ($context['request_id'] ?? null) === 'req_test_400'
            && ($context['endpoint'] ?? null) === '/chat/completions'
            && ($context['tools_included'] ?? null) === false
            && ! str_contains($encoded, 'sk-proj-SECRETVALUE1234567890')
            && ! str_contains($encoded, 'test-key-not-real')
            && str_contains((string) ($context['error_message'] ?? ''), '[redacted]');
    });
});

test('openai provider maps http 401 invalid api key to configuration message', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Invalid API Key',
                'type' => 'invalid_request_error',
                'code' => 'invalid_api_key',
            ],
        ], 401),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiInvalidResponseException::class,
        'AI configuration requires administrator attention.',
    );
});

test('openai provider maps groq-style network 403 to connection message not configuration', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'Access denied. Please check your network settings.',
            ],
        ], 403),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiConnectionException::class,
        'The AI provider blocked this network request. Check VPN, firewall, proxy, or regional access, then try again.',
    );
});

test('openai provider maps credential-style 403 to configuration message', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => ['message' => 'API key lacks permission for this model', 'type' => 'permission', 'code' => 'permission'],
        ], 403),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiInvalidResponseException::class,
        'AI configuration requires administrator attention.',
    );
});

test('openai provider uses optional http proxy when configured', function () {
    config(['ai.openai.http_proxy' => 'http://127.0.0.1:7890']);

    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'proxied'],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    $result = app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]);

    expect($result->content)->toBe('proxied');
});

test('openai provider maps http 404 to unsupported model message', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => ['message' => 'Not found', 'type' => 'not_found', 'code' => 'not_found'],
        ], 404),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiInvalidResponseException::class,
        'The configured AI model is unavailable or unsupported.',
    );
});

test('openai provider maps http 500 to connection message', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response(['error' => 'boom'], 500),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiConnectionException::class,
        'Unable to connect to the AI provider. Please try again.',
    );
});

test('openai provider maps invalid tool schema rejection safely', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'error' => [
                'message' => 'tools.0.function.parameters is invalid',
                'type' => 'invalid_request_error',
                'code' => 'invalid_request_error',
            ],
        ], 400),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat(
        [['role' => 'user', 'content' => 'Find stock']],
        [
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'broken_tool',
                    'description' => 'broken',
                    'parameters' => ['type' => 'bad'],
                ],
            ]],
        ],
    ))->toThrow(
        AiInvalidResponseException::class,
        'The AI request could not be processed. Please try again.',
    );
});

test('openai provider rejects empty assistant content', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => '']],
            ],
        ], 200),
    ]);

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(AiInvalidResponseException::class);
});

test('openai provider accepts tool calls with empty content and sends tools payload', function () {
    Http::fake([
        'api.openai.test/v1/chat/completions' => Http::response([
            'model' => 'gpt-test',
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_abc',
                        'type' => 'function',
                        'function' => [
                            'name' => 'search_products',
                            'arguments' => '{"query":"Dior"}',
                        ],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
            'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12],
        ], 200),
    ]);

    $result = app(OpenAiProvider::class)->chat(
        [['role' => 'user', 'content' => 'Find Dior']],
        [
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search products',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ]],
            'tool_choice' => 'auto',
        ],
    );

    expect($result->hasToolCalls())->toBeTrue()
        ->and($result->content)->toBe('')
        ->and($result->toolCalls[0]->name)->toBe('search_products')
        ->and($result->toolCalls[0]->arguments['query'])->toBe('Dior')
        ->and($result->finishReason)->toBe('tool_calls');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return isset($data['tools'][0]['function']['name'])
            && $data['tools'][0]['function']['name'] === 'search_products'
            && ($data['tool_choice'] ?? null) === 'auto'
            && array_key_exists('parallel_tool_calls', $data)
            && $data['parallel_tool_calls'] === false;
    });
});

test('openai-compatible provider posts to groq-style base url without organization header', function () {
    config([
        'ai.openai.base_url' => 'https://api.groq.com/openai/v1',
        'ai.openai.model' => 'openai/gpt-oss-120b',
        'ai.openai.organization' => null,
        'ai.parallel_tool_calls' => false,
    ]);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'model' => 'openai/gpt-oss-120b',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'Groq ok'],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    $result = app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]);

    expect($result->content)->toBe('Groq ok');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && ($request->data()['model'] ?? null) === 'openai/gpt-oss-120b'
            && ! $request->hasHeader('OpenAI-Organization')
            && $request->hasHeader('Authorization', 'Bearer test-key-not-real');
    });
});

test('openai provider requires api key when enabled', function () {
    config(['ai.openai.api_key' => '']);

    Log::spy();

    expect(fn () => app(OpenAiProvider::class)->chat([
        ['role' => 'user', 'content' => 'Hi'],
    ]))->toThrow(
        AiConnectionException::class,
        'AI configuration requires administrator attention.',
    );

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) {
        return $message === 'ai.provider.missing_api_key'
            && ($context['provider'] ?? null) === 'openai'
            && ! str_contains(json_encode($context), 'sk-');
    });
});

test('openai provider does not duplicate slash when base url already ends with v1', function () {
    config(['ai.openai.base_url' => 'https://api.groq.com/openai/v1/']);

    Http::fake([
        'api.groq.com/openai/v1/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => 'ok'],
                'finish_reason' => 'stop',
            ]],
        ], 200),
    ]);

    app(OpenAiProvider::class)->chat([['role' => 'user', 'content' => 'Hi']]);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions');
});
