<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Tokens;

use Duxbo\Seo\Contracts\TokenResolver;
use Duxbo\Seo\Data\SeoContext;

/**
 * Reads an arbitrary attribute by name — `%field(price)%`.
 *
 * The escape hatch that means a project rarely needs to register a token of its
 * own just to reach one more column.
 */
final class FieldToken implements TokenResolver
{
    public function key(): string
    {
        return 'field';
    }

    public function resolve(SeoContext $context, ?string $argument = null): ?string
    {
        if ($argument === null || $argument === '') {
            return null;
        }

        $value = $context->attribute($argument);

        return match (true) {
            is_string($value) && trim($value) !== '' => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => null,
        };
    }
}
