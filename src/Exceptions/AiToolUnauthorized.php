<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class AiToolUnauthorized extends RuntimeException implements SeoException
{
    public static function forTool(string $name, string $ability): self
    {
        return new self(sprintf(
            'Not authorized to call AI tool [%s]: missing the [%s] Gate ability.',
            $name,
            $ability,
        ));
    }
}
