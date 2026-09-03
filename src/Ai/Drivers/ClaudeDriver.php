<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;

/**
 * Anthropic's Messages API.
 *
 * Structured output is obtained by declaring a tool whose input schema is the
 * shape we want and then requiring that tool — the model fills the schema
 * rather than writing prose we would have to parse.
 */
final class ClaudeDriver extends HttpDriver
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public function name(): string
    {
        return 'claude';
    }

    public function complete(AiRequest $request): AiResponse
    {
        $tool = [
            'name' => 'seo_result',
            'description' => 'Return the requested SEO values.',
            'input_schema' => $request->schema,
        ];

        $payload = [
            'model' => $this->model($request),
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
            'tools' => [$tool],
            // Forcing the tool is what makes the response a schema-shaped
            // object rather than a sentence describing one.
            'tool_choice' => ['type' => 'tool', 'name' => 'seo_result'],
            'messages' => [
                ['role' => 'user', 'content' => $request->prompt],
            ],
        ];

        if ($request->system !== null) {
            $payload['system'] = $request->system;
        }

        $body = $this->decode(
            $this->client()
                ->withHeaders([
                    'x-api-key' => $this->apiKey(),
                    'anthropic-version' => self::VERSION,
                ])
                ->post(self::ENDPOINT, $payload),
        );

        $content = null;

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && is_array($block['input'] ?? null)) {
                $content = $block['input'];

                break;
            }
        }

        if ($content === null) {
            throw \Duxbo\Seo\Exceptions\AiRequestFailed::notStructured($this->name());
        }

        return new AiResponse(
            content: $content,
            driver: $this->name(),
            model: is_string($body['model'] ?? null) ? $body['model'] : null,
            inputTokens: (int) ($body['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($body['usage']['output_tokens'] ?? 0),
            raw: $body,
        );
    }
}
