<?php

declare(strict_types=1);

namespace Duxbo\Seo\Enums;

/**
 * How a redirect rule's source is compared against an incoming path.
 */
enum RedirectMatchType: string
{
    /** Whole path must be identical. Resolved by hash lookup, O(1). */
    case Exact = 'exact';

    /** Path must start with the source. Longest match wins. */
    case Prefix = 'prefix';

    /** Source is a PCRE pattern. Slowest, and validated on save. */
    case Regex = 'regex';

    /**
     * Rules are evaluated cheapest-first; lower sorts earlier.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Exact => 0,
            self::Prefix => 1,
            self::Regex => 2,
        };
    }
}
