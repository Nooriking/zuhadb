<?php

namespace App\Ai\Providers;

use App\Ai\Contracts\AiProvider;
use App\Ai\DTOs\AiCompletionResult;
use App\Ai\DTOs\AiToolCall;
use App\Ai\Exceptions\AiConnectionException;
use App\Ai\Exceptions\AiDisabledException;
use App\Ai\Exceptions\AiInvalidResponseException;
use App\Ai\Exceptions\AiRateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        if (! config('ai.enabled')) {
            throw AiDisabledException::make();
        }

        $apiKey = (string) config('ai.openai.api_key');
        if ($apiKey === '') {
            Log::warning('ai.provider.missing_api_key', [
                'provider' => $this->name(),
            ]);

            throw AiConnectionException::make('AI configuration requires administrator attention.');
        }

        $baseUrl = rtrim((string) config('ai.openai.base_url'), '/');
        $model = (string) ($options['model'] ?? config('ai.openai.model'));
        $timeout = max(1, (int) config('ai.timeout', 30));
        $temperature = array_key_exists('temperature', $options)
            ? (float) $options['temperature']
            : (float) config('ai.temperature', 0.2);
        $maxTokens = array_key_exists('max_tokens', $options)
            ? (int) $options['max_tokens']
            : (int) config('ai.max_tokens', 2048);

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $organization = config('ai.openai.organization');
        if (is_string($organization) && $organization !== '') {
            $headers['OpenAI-Organization'] = $organization;
        }

        // Groq prefers max_completion_tokens; max_tokens is deprecated on their API.
        $payload = [
            'model' => $model,
            'messages' => array_values($messages),
            'temperature' => $temperature,
            'max_completion_tokens' => $maxTokens,
        ];

        $tools = $options['tools'] ?? null;
        $toolsIncluded = is_array($tools) && $tools !== [];
        if ($toolsIncluded) {
            $payload['tools'] = array_values($tools);
            $payload['tool_choice'] = $options['tool_choice'] ?? 'auto';
            // gpt-oss does not support parallel tool use; default comes from config/ai.php.
            $payload['parallel_tool_calls'] = (bool) ($options['parallel_tool_calls']
                ?? config('ai.parallel_tool_calls', false));
        }

        try {
            $pending = Http::baseUrl($baseUrl)
                ->withHeaders($headers)
                ->timeout($timeout)
                ->acceptJson()
                ->asJson();

            $proxy = config('ai.openai.http_proxy');
            if (is_string($proxy) && $proxy !== '') {
                $pending = $pending->withOptions(['proxy' => $proxy]);
            }

            $response = $pending->post('/chat/completions', $payload);
        } catch (ConnectionException $e) {
            $this->logFailure('connection', $e);

            throw AiConnectionException::make('Unable to connect to the AI provider. Please try again.');
        } catch (Throwable $e) {
            $this->logFailure('unexpected', $e);

            throw AiConnectionException::make('Unable to connect to the AI provider. Please try again.');
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            $seconds = is_numeric($retryAfter) ? (int) $retryAfter : null;
            Log::warning('ai.provider.rate_limited', [
                'provider' => $this->name(),
                'status' => 429,
                'retry_after' => $seconds,
                'model' => $model,
                'endpoint' => '/chat/completions',
            ]);

            throw AiRateLimitException::make($seconds);
        }

        if ($response->failed()) {
            $this->logHttpError($response, $model, $toolsIncluded, is_array($tools) ? count($tools) : 0);

            throw $this->exceptionForFailedResponse($response);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw AiInvalidResponseException::make();
        }

        $message = data_get($json, 'choices.0.message');
        if (! is_array($message)) {
            throw AiInvalidResponseException::make('AI provider returned no message.');
        }

        $toolCalls = $this->parseToolCalls($message['tool_calls'] ?? null);
        $content = $message['content'] ?? null;
        $contentString = is_string($content) ? $content : '';

        if ($toolCalls === [] && $contentString === '') {
            throw AiInvalidResponseException::make('AI provider returned empty content.');
        }

        $finishReason = data_get($json, 'choices.0.finish_reason');
        $usage = data_get($json, 'usage');

        return new AiCompletionResult(
            content: $contentString,
            model: (string) (data_get($json, 'model') ?: $model),
            finishReason: is_string($finishReason) ? $finishReason : null,
            metadata: [
                'usage' => is_array($usage) ? [
                    'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                    'completion_tokens' => $usage['completion_tokens'] ?? null,
                    'total_tokens' => $usage['total_tokens'] ?? null,
                ] : [],
            ],
            toolCalls: $toolCalls,
        );
    }

    /**
     * @return list<AiToolCall>
     */
    private function parseToolCalls(mixed $rawCalls): array
    {
        if (! is_array($rawCalls) || $rawCalls === []) {
            return [];
        }

        $parsed = [];

        foreach ($rawCalls as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $id = isset($raw['id']) && is_string($raw['id']) ? $raw['id'] : '';
            $function = is_array($raw['function'] ?? null) ? $raw['function'] : [];
            $name = isset($function['name']) && is_string($function['name']) ? trim($function['name']) : '';
            $rawArguments = isset($function['arguments']) && is_string($function['arguments'])
                ? $function['arguments']
                : '{}';

            if ($id === '' || $name === '') {
                throw AiInvalidResponseException::make('AI provider returned an invalid tool call.');
            }

            $arguments = json_decode($rawArguments, true);
            if (! is_array($arguments)) {
                // Keep execution path safe: invalid JSON becomes empty args and fails tool validation.
                $arguments = [];
            }

            $parsed[] = new AiToolCall(
                id: $id,
                name: $name,
                arguments: $arguments,
                rawArguments: $rawArguments,
            );
        }

        return $parsed;
    }

    private function logFailure(string $kind, Throwable $e): void
    {
        Log::warning('ai.provider.'.$kind.'_error', [
            'provider' => $this->name(),
            'exception' => $e::class,
            // Never log API keys, Authorization headers, or message payloads.
            'message' => $e instanceof RequestException
                ? 'HTTP request failed'
                : 'Provider request failed',
        ]);
    }

    /**
     * Structured, secret-safe provider HTTP failure diagnostics.
     */
    private function logHttpError(Response $response, string $model, bool $toolsIncluded, int $toolsCount): void
    {
        $json = $response->json();
        $error = is_array($json) && is_array($json['error'] ?? null) ? $json['error'] : [];

        $rawMessage = isset($error['message']) && is_string($error['message']) ? $error['message'] : null;
        $errorType = isset($error['type']) && is_string($error['type']) ? $error['type'] : null;
        $errorCode = $error['code'] ?? null;
        if (is_scalar($errorCode)) {
            $errorCode = (string) $errorCode;
        } else {
            $errorCode = null;
        }

        $requestId = $response->header('x-request-id')
            ?: $response->header('x-groq-request-id')
            ?: null;

        Log::warning('ai.provider.http_error', [
            'provider' => $this->name(),
            'status' => $response->status(),
            'error_type' => $errorType,
            'error_code' => $errorCode,
            'error_message' => $this->sanitizeProviderMessage($rawMessage),
            'request_id' => $requestId,
            'endpoint' => '/chat/completions',
            'model' => $model,
            'tools_included' => $toolsIncluded,
            'tools_count' => $toolsCount,
        ]);
    }

    private function exceptionForFailedResponse(Response $response): AiConnectionException|AiInvalidResponseException
    {
        $status = $response->status();

        if ($response->serverError()) {
            return AiConnectionException::make('Unable to connect to the AI provider. Please try again.');
        }

        if ($status === 401) {
            // Invalid/expired API key or auth header — true configuration problem.
            return AiInvalidResponseException::make('AI configuration requires administrator attention.');
        }

        if ($status === 403) {
            // Groq/Cloudflare often returns 403 "Access denied. Please check your network settings"
            // for VPN, datacenter, or region blocks — not a missing local API key.
            if ($this->isProviderNetworkDenial($response)) {
                return AiConnectionException::make(
                    'The AI provider blocked this network request. Check VPN, firewall, proxy, or regional access, then try again.'
                );
            }

            return AiInvalidResponseException::make('AI configuration requires administrator attention.');
        }

        if ($status === 404) {
            return AiInvalidResponseException::make('The configured AI model is unavailable or unsupported.');
        }

        // Includes 400 invalid_request_error (bad model id, schema, params, etc.).
        return AiInvalidResponseException::make('The AI request could not be processed. Please try again.');
    }

    /**
     * Detect provider/WAF network or geo denials that are not local credential misconfiguration.
     */
    private function isProviderNetworkDenial(Response $response): bool
    {
        $json = $response->json();
        $error = is_array($json) && is_array($json['error'] ?? null) ? $json['error'] : [];
        $message = isset($error['message']) && is_string($error['message'])
            ? mb_strtolower($error['message'])
            : mb_strtolower((string) $response->body());

        if ($message === '') {
            return false;
        }

        return str_contains($message, 'network settings')
            || str_contains($message, 'access denied')
            || str_contains($message, 'not available in your region')
            || str_contains($message, 'forbidden')
            || str_contains($message, 'cloudflare');
    }

    private function sanitizeProviderMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        // Strip accidental secrets and truncate for safe structured logs.
        $safe = preg_replace('/(?:sk-|gsk_|Bearer\s+)[A-Za-z0-9_\-]{8,}/i', '[redacted]', $message) ?? $message;
        $safe = preg_replace('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', '[redacted-email]', $safe) ?? $safe;

        return mb_substr($safe, 0, 240);
    }
}
