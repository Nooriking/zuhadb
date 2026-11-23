<?php

namespace App\Ai\Tools;

use App\Ai\DTOs\AiToolCall;
use App\Ai\DTOs\AiToolExecutionResult;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Executes only registered tools after authorization and argument validation.
 * Never runs arbitrary code. SQL from query_database is validated by ReadOnlyQueryEngine.
 */
final class AiToolCaller
{
    public function __construct(
        private readonly AiToolRegistry $registry,
    ) {}

    public function execute(User $user, AiToolCall $call): AiToolExecutionResult
    {
        if (! $this->registry->has($call->name)) {
            Log::warning('ai.tool.unknown', [
                'tool' => $call->name,
                'user_id' => $user->id,
            ]);

            return new AiToolExecutionResult(
                toolCallId: $call->id,
                toolName: $call->name,
                ok: false,
                payload: [
                    'error' => 'unknown_tool',
                    'message' => 'That tool is not available.',
                ],
            );
        }

        $tool = $this->registry->get($call->name);

        if (! $tool->authorize($user)) {
            Log::info('ai.tool.unauthorized', [
                'tool' => $tool->name(),
                'user_id' => $user->id,
            ]);

            return new AiToolExecutionResult(
                toolCallId: $call->id,
                toolName: $tool->name(),
                ok: false,
                payload: [
                    'error' => 'unauthorized',
                    'message' => 'You do not have permission to use this tool.',
                ],
            );
        }

        $validator = Validator::make($call->arguments, $this->laravelRulesFromSchema($tool->parametersSchema()));

        if ($validator->fails()) {
            return new AiToolExecutionResult(
                toolCallId: $call->id,
                toolName: $tool->name(),
                ok: false,
                payload: [
                    'error' => 'invalid_arguments',
                    'message' => 'Tool arguments failed validation.',
                    'details' => $validator->errors()->toArray(),
                ],
            );
        }

        try {
            $payload = $tool->execute($user, $validator->validated());
        } catch (Throwable $e) {
            Log::warning('ai.tool.execution_failed', [
                'tool' => $tool->name(),
                'user_id' => $user->id,
                'exception' => $e::class,
            ]);

            return new AiToolExecutionResult(
                toolCallId: $call->id,
                toolName: $tool->name(),
                ok: false,
                payload: [
                    'error' => 'execution_failed',
                    'message' => 'The tool could not complete this request.',
                ],
            );
        }

        return new AiToolExecutionResult(
            toolCallId: $call->id,
            toolName: $tool->name(),
            ok: true,
            payload: $payload,
        );
    }

    /**
     * Minimal JSON-Schema → Laravel rules mapping for our known tool shapes.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, list<string>|string>
     */
    private function laravelRulesFromSchema(array $schema): array
    {
        $rules = [];
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

        foreach ($properties as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                continue;
            }

            $fieldRules = [];
            $fieldRules[] = in_array($name, $required, true) ? 'required' : 'nullable';

            $type = $definition['type'] ?? null;
            if ($type === 'string') {
                $fieldRules[] = 'string';
                if (isset($definition['maxLength']) && is_numeric($definition['maxLength'])) {
                    $fieldRules[] = 'max:'.(int) $definition['maxLength'];
                }
                if (isset($definition['minLength']) && is_numeric($definition['minLength'])) {
                    $fieldRules[] = 'min:'.(int) $definition['minLength'];
                }
            } elseif ($type === 'integer') {
                $fieldRules[] = 'integer';
                if (isset($definition['minimum']) && is_numeric($definition['minimum'])) {
                    $fieldRules[] = 'min:'.(int) $definition['minimum'];
                }
                if (isset($definition['maximum']) && is_numeric($definition['maximum'])) {
                    $fieldRules[] = 'max:'.(int) $definition['maximum'];
                }
            } elseif ($type === 'number') {
                $fieldRules[] = 'numeric';
            } elseif ($type === 'boolean') {
                $fieldRules[] = 'boolean';
            } elseif ($type === 'object' || $type === 'array') {
                $fieldRules[] = 'array';
            }

            $rules[$name] = $fieldRules;
        }

        return $rules;
    }
}
