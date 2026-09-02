<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Tokens;

use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Support\Carbon;

/**
 * The current date — `%currentyear%`, `%currentdate%`.
 *
 * Uses Carbon rather than date() so `Carbon::setTestNow()` freezes it in tests,
 * and so a title reading "Best laptops 2026" is verifiable.
 */
final class NowToken implements TokenResolver
{
    public function __construct(
        private readonly string $key,
        private readonly string $format,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function resolve(SeoContext $context, ?string $argument = null): ?string
    {
        return Carbon::now()->format($argument ?? $this->format);
    }
}
