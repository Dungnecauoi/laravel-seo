<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class InvalidSeoData extends InvalidArgumentException implements SeoException
{
    public static function unknownAttribute(string $key, string $dto): self
    {
        return new self(sprintf('Unknown attribute [%s] for %s.', $key, $dto));
    }

    public static function directiveNeedsValue(string $directive): self
    {
        return new self(sprintf('Robots directive [%s] requires a value.', $directive));
    }

    public static function directiveTakesNoValue(string $directive): self
    {
        return new self(sprintf('Robots directive [%s] does not take a value.', $directive));
    }
}
