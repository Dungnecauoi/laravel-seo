<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;

/**
 * OpenAI's Chat Completions API.
 *
 * Structured output via `response_format: json_schema` in strict mode, so the
 * body is a JSON object conforming to the schema rather than prose.
 */
final class OpenAiDriver extends HttpDriver
{
    public function name(): string
    {
        return 'openai';
    }

    public function complete(AiRequest $request): AiResponse
    {
        $messages = [];

        if ($request->system !== null) {
            $messages[] = ['role' => 'system', 'content' => $request->system];
        }

        $messages[] = ['role' => 'user', 'content' => $request->prompt];

        $body = $this->decode(
            $this->client()
                ->withToken($this->apiKey())
                ->post($this->endpoint(), [
                    'model' => $this->model($request),
                    'temperature' => $request->temperature,
                    'max_completion_tokens' => $request->maxTokens,
                    'messages' => $messages,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'seo_result',
                            // Strict mode is what turns the schema from a hint
                            // into a guarantee.
                            'strict' => true,
                            'schema' => $this->strictSchema($request->schema),
                        ],
                    ],
                ]),
        );

        $text = $body['choices'][0]['message']['content'] ?? null;

        if (! is_string($text)) {
            throw \Duxbo\Seo\Exceptions\AiRequestFailed::notStructured($this->name());
        }

        return new AiResponse(
            content: $this->decodeJsonString($text),
            driver: $this->name(),
            model: is_string($body['model'] ?? null) ? $body['model'] : null,
            inputTokens: (int) ($body['usage']['prompt_tokens'] ?? 0),
            outputTokens: (int) ($body['usage']['completion_tokens'] ?? 0),
            raw: $body,
        );
    }

    /**
     * Strict mode rejects a schema that allows extra properties or leaves a
     * property optional, so both are pinned down here rather than requiring
     * every caller to remember.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function strictSchema(array $schema): array
    {
        $schema['additionalProperties'] ??= false;

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $schema['required'] ??= array_keys($schema['properties']);
        }

        return $schema;
    }

    private function endpoint(): string
    {
        $base = $this->config['base_url'] ?? 'https://api.openai.com/v1';

        return rtrim((string) $base, '/').'/chat/completions';
    }
}
