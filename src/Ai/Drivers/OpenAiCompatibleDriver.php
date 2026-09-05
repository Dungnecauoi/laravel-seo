<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;
use Duxbo\Seo\Exceptions\AiRequestFailed;

/**
 * The Chat Completions request and response shape OpenAI defined and
 * several other providers copied deliberately, precisely so a client
 * written for one needs only a different base URL to speak to another —
 * {@see OpenAiDriver}, {@see GroqDriver} and {@see OpenRouterDriver} are
 * this one class with nothing but that URL, and each's own extra headers
 * where it has any, differing.
 *
 * Structured output via `response_format: json_schema` in strict mode, so
 * the body is a JSON object conforming to the schema rather than prose —
 * supported by OpenAI itself and by most models Groq and OpenRouter serve,
 * though not guaranteed for every model either of the latter two host: a
 * model that does not honour it fails loudly through the same path a bad
 * API key would, rather than silently returning prose to parse.
 */
abstract class OpenAiCompatibleDriver extends HttpDriver
{
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
            throw AiRequestFailed::notStructured($this->name());
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
     * The host to send Chat Completions requests to, without a trailing
     * `/chat/completions` — overridable through `base_url` in this driver's
     * own config block, which is how any other OpenAI-compatible endpoint
     * (a self-hosted vLLM server, for instance) can be reached without a
     * dedicated driver class at all.
     */
    abstract protected function defaultBaseUrl(): string;

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
        $base = $this->config['base_url'] ?? $this->defaultBaseUrl();

        return rtrim((string) $base, '/').'/chat/completions';
    }
}
