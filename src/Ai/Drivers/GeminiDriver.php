<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;

/**
 * Google's Generative Language API.
 *
 * Structured output via `responseSchema` with a JSON mime type.
 */
final class GeminiDriver extends HttpDriver
{
    public function name(): string
    {
        return 'gemini';
    }

    public function complete(AiRequest $request): AiResponse
    {
        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $request->prompt]]],
            ],
            'generationConfig' => [
                'temperature' => $request->temperature,
                'maxOutputTokens' => $request->maxTokens,
                'responseMimeType' => 'application/json',
                'responseSchema' => $this->toGeminiSchema($request->schema),
            ],
        ];

        if ($request->system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $request->system]]];
        }

        // The key goes in a header rather than the query string, so it cannot
        // end up in an access log or a proxy's request record.
        $body = $this->decode(
            $this->client()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->post($this->endpoint($request), $payload),
        );

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text)) {
            throw \Duxbo\Seo\Exceptions\AiRequestFailed::notStructured($this->name());
        }

        return new AiResponse(
            content: $this->decodeJsonString($text),
            driver: $this->name(),
            model: $this->model($request),
            inputTokens: (int) ($body['usageMetadata']['promptTokenCount'] ?? 0),
            outputTokens: (int) ($body['usageMetadata']['candidatesTokenCount'] ?? 0),
            raw: $body,
        );
    }

    /**
     * Gemini takes a subset of JSON Schema with upper-case type names and no
     * `additionalProperties`, so a schema written for the other drivers is
     * translated here rather than duplicated by the caller.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function toGeminiSchema(array $schema): array
    {
        $converted = [];

        foreach ($schema as $key => $value) {
            if ($key === 'additionalProperties') {
                continue;
            }

            if ($key === 'type' && is_string($value)) {
                $converted['type'] = strtoupper($value);

                continue;
            }

            $converted[$key] = is_array($value) ? $this->convertNested($value) : $value;
        }

        return $converted;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function convertNested(array $value): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            if ($key === 'additionalProperties') {
                continue;
            }

            if ($key === 'type' && is_string($item)) {
                $out['type'] = strtoupper($item);

                continue;
            }

            $out[$key] = is_array($item) ? $this->convertNested($item) : $item;
        }

        return $out;
    }

    private function endpoint(AiRequest $request): string
    {
        $base = $this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';

        return rtrim((string) $base, '/').'/models/'.$this->model($request).':generateContent';
    }
}
