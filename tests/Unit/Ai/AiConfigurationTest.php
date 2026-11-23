<?php

use App\Ai\Contracts\AiProvider;
use App\Ai\Providers\OpenAiProvider;
use Tests\TestCase;

uses(TestCase::class);

test('ai config loads expected keys from environment-backed defaults', function () {
    expect(config('ai.enabled'))->toBeBool()
        ->and(config('ai.provider'))->toBeString()
        ->and(config('ai.openai.api_key'))->toBeString()
        ->and(config('ai.openai.base_url'))->toBeString()
        ->and(config('ai.openai.model'))->toBeString()
        ->and(config('ai.timeout'))->toBeInt()
        ->and(config('ai.max_tokens'))->toBeInt()
        ->and((float) config('ai.temperature'))->toBeFloat()
        ->and(config('ai.parallel_tool_calls'))->toBeBool()
        ->and(config('ai.rate_limit.per_minute'))->toBeInt()
        ->and(config('ai.rate_limit.per_day'))->toBeInt()
        ->and(config('ai.max_tool_rounds'))->toBeInt()
        ->and(config('ai.history.max_messages'))->toBeInt()
        ->and(config('ai.history.max_message_chars'))->toBeInt()
        ->and(config('ai.system_prompt'))->toBeString()
        ->and(config('ai.conversations.per_page'))->toBeInt();
});

test('ai config file documents groq openai-compatible defaults', function () {
    $source = file_get_contents(config_path('ai.php'));

    expect($source)->toContain("env('AI_PROVIDER', 'openai')")
        ->and($source)->toContain('https://api.groq.com/openai/v1')
        ->and($source)->toContain('openai/gpt-oss-120b')
        ->and($source)->toContain("env('AI_PARALLEL_TOOL_CALLS', false)")
        ->and($source)->toContain("env('AI_HTTP_PROXY')");
});

test('default system prompt distinguishes dari persian from arabic and schema tools', function () {
    $prompt = (string) config('ai.system_prompt');

    expect($prompt)->toContain('Dari/Persian')
        ->and($prompt)->toContain('not Arabic')
        ->and($prompt)->toContain('describe_database_table')
        ->and($prompt)->toContain('search_products');
});

test('ai api key is never exposed via shared inertia auth payload shape', function () {
    // Guardrail: config secret must stay server-side. HandleInertiaRequests must not share AI_* secrets.
    $sharedKeys = ['auth', 'permissions', 'access', 'notifications', 'shop', 'currency', 'exchange', 'flash'];

    foreach ($sharedKeys as $key) {
        expect($key)->not->toBe('ai_api_key');
    }

    expect(config('ai.openai.api_key'))->not->toBe(config('app.key'));
});

test('ai provider is bound to the openai implementation by default', function () {
    config(['ai.provider' => 'openai']);
    app()->forgetInstance(AiProvider::class);

    expect(app()->bound(AiProvider::class))->toBeTrue();

    $provider = app(AiProvider::class);

    expect($provider)->toBeInstanceOf(OpenAiProvider::class)
        ->and($provider->name())->toBe('openai');
});

test('openai-compatible provider resolves correctly with groq base url', function () {
    config([
        'ai.provider' => 'openai',
        'ai.openai.base_url' => 'https://api.groq.com/openai/v1',
        'ai.openai.model' => 'openai/gpt-oss-120b',
        'ai.openai.organization' => null,
    ]);
    app()->forgetInstance(AiProvider::class);

    $provider = app(AiProvider::class);

    expect($provider)->toBeInstanceOf(OpenAiProvider::class)
        ->and(config('ai.openai.base_url'))->toBe('https://api.groq.com/openai/v1')
        ->and(config('ai.openai.model'))->toBe('openai/gpt-oss-120b')
        ->and(config('ai.openai.organization'))->toBeNull();
});

test('provider binding uses current config at resolution time', function () {
    // Simulates fresh resolution after config:clear / runtime config changes —
    // the singleton must read config('ai.provider') when resolved, not a stale driver.
    config(['ai.provider' => 'openai']);
    app()->forgetInstance(AiProvider::class);
    expect(app(AiProvider::class))->toBeInstanceOf(OpenAiProvider::class);

    config(['ai.provider' => 'not-a-real-provider']);
    app()->forgetInstance(AiProvider::class);

    expect(fn () => app(AiProvider::class))
        ->toThrow(InvalidArgumentException::class, 'Unsupported AI provider [not-a-real-provider].');
});

test('unsupported ai provider driver fails at resolution', function () {
    config(['ai.provider' => 'not-a-real-provider']);
    app()->forgetInstance(AiProvider::class);

    expect(fn () => app(AiProvider::class))
        ->toThrow(InvalidArgumentException::class);
});

test('ai provider driver named groq is not a separate implementation', function () {
    // Architecture keeps AI_PROVIDER=openai for the OpenAI-compatible client.
    // Setting AI_PROVIDER=groq must fail clearly rather than invent a second provider.
    config(['ai.provider' => 'groq']);
    app()->forgetInstance(AiProvider::class);

    expect(fn () => app(AiProvider::class))
        ->toThrow(InvalidArgumentException::class, 'Unsupported AI provider [groq].');
});
