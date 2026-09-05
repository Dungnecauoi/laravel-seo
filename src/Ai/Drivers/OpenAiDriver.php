<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

/**
 * OpenAI's own Chat Completions API — see {@see OpenAiCompatibleDriver} for
 * the request and response handling itself, shared with every other
 * provider that copies this exact shape.
 */
final class OpenAiDriver extends OpenAiCompatibleDriver
{
    public function name(): string
    {
        return 'openai';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }
}
