<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class AiToolNotFound extends InvalidArgumentException implements SeoException
{
    /**
     * @param  list<string>  $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(sprintf(
            'No AI tool named [%s]. Registered: %s.',
            $name,
            $known === [] ? 'none' : implode(', ', $known),
        ));
    }
}
