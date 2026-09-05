<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class UnsafeCanonical extends InvalidArgumentException implements SeoException
{
    /**
     * @param  list<string>  $chain
     */
    public static function cycle(array $chain): self
    {
        return new self(sprintf(
            'Refusing a canonical cycle: %s. Each page in the chain would tell search '
            .'engines the next one is the "real" version, with nothing ever settling on one.',
            implode(' → ', $chain),
        ));
    }

    /**
     * @param  list<string>  $chain
     */
    public static function chainTooLong(array $chain): self
    {
        return new self(sprintf(
            'Canonical chain is longer than %d hops: %s. Point the canonical directly at '
            .'the final destination instead of through a chain of pages that each defer '
            .'to the next.',
            count($chain),
            implode(' → ', $chain),
        ));
    }
}
