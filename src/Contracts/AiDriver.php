<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;

/**
 * Talks to one language model provider.
 *
 * Implementations call the provider's REST endpoint through Laravel's HTTP
 * client. No vendor SDK is required, or even suggested — the APIs are plain
 * documented HTTP, and an SDK dependency would be one more library whose
 * abandonment becomes this package's problem.
 */
interface AiDriver
{
    /**
     * Send one request and return the structured result.
     *
     * Implementations must ask the provider for schema-constrained output —
     * Claude's tool use, OpenAI's json_schema, Gemini's responseSchema — and
     * never scrape values out of prose with regular expressions.
     */
    public function complete(AiRequest $request): AiResponse;

    /**
     * Driver name as registered, e.g. `claude`.
     */
    public function name(): string;
}
