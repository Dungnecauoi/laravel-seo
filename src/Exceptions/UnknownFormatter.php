<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class UnknownFormatter extends InvalidArgumentException implements SeoException
{
    /**
     * @param  list<string>  $available
     */
    public static function named(string $name, array $available): self
    {
        return new self(sprintf(
            'No SEO formatter named [%s]. Registered: %s. Add one with Seo::registerFormatter().',
            $name,
            $available === [] ? 'none' : implode(', ', $available),
        ));
    }
}
