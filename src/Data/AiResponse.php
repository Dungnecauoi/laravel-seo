<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * A driver's answer, already conforming to the request's schema.
 */
final class AiResponse
{
    /**
     * @param  array<string, mixed>  $content  Decoded, schema-shaped result.
     * @param  array<string, mixed>  $raw  Untouched provider payload, for debugging.
     */
    public function __construct(
        public readonly array $content,
        public readonly string $driver,
        public readonly ?string $model = null,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly array $raw = [],
        public readonly bool $fromCache = false,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->content[$key] ?? $default;
    }

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
