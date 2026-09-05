<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

/**
 * Groq's OpenAI-compatible Chat Completions endpoint — see
 * {@see OpenAiCompatibleDriver} for the request and response handling
 * itself. Groq serves open-weight models (Llama, and others), so
 * `seo.ai.drivers.groq.model` needs to name one that actually supports
 * `response_format: json_schema` — not every model on the platform does.
 */
final class GroqDriver extends OpenAiCompatibleDriver
{
    public function name(): string
    {
        return 'groq';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.groq.com/openai/v1';
    }
}
