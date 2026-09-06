<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class UnsafeCanonical extends InvalidArgumentException implements SeoException
{
    /**
     * The same off-site check {@see \Duxbo\Seo\Http\Api\V1\MetaController::update()}
     * runs through Laravel's validator — reused directly by callers that
     * don't go through HTTP validation, such as an AI tool.
     *
     * @param  list<string>  $allowed
     */
    public static function hostNotAllowed(string $host, array $allowed): self
    {
        return new self(sprintf(
            'Refusing a canonical pointed at [%s]. It tells search engines this page\'s real home is '
            .'elsewhere and can pull it out of the index entirely. Allowed hosts: %s. Add one under '
            .'seo.redirects.allowed_hosts if it is genuinely yours.',
            $host,
            $allowed === [] ? 'none besides your own' : implode(', ', $allowed),
        ));
    }

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
