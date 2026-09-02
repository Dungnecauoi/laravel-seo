<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Data\Concerns\Copyable;

/**
 * A provider-agnostic prompt.
 *
 * `schema` is a JSON Schema describing the expected answer. Every driver must
 * enforce it through its provider's own structured-output mechanism; parsing
 * values back out of prose is not an acceptable fallback.
 */
final class AiRequest
{
    use Copyable;

    /**
     * @param  array<string, mixed>  $schema  JSON Schema for the response object.
     * @param  array<string, mixed>  $metadata  Carried through to logging; never sent upstream.
     */
    public function __construct(
        public readonly string $prompt,
        public readonly array $schema,
        public readonly ?string $system = null,
        public readonly ?string $locale = null,
        public readonly float $temperature = 0.3,
        public readonly int $maxTokens = 1024,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Stable key for caching, so the same content is never billed twice.
     */
    public function cacheKey(): string
    {
        return 'seo:ai:'.md5(serialize([
            $this->prompt,
            $this->schema,
            $this->system,
            $this->locale,
            $this->temperature,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    protected function constructorArgs(): array
    {
        return [
            'prompt' => $this->prompt,
            'schema' => $this->schema,
            'system' => $this->system,
            'locale' => $this->locale,
            'temperature' => $this->temperature,
            'maxTokens' => $this->maxTokens,
            'metadata' => $this->metadata,
        ];
    }
}
