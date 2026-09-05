<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use InvalidArgumentException;

final class InvalidSettingValue extends InvalidArgumentException implements SeoException
{
    public static function make(string $key, string $reason): self
    {
        return new self(sprintf('Invalid value for dynamic setting [%s]: %s', $key, $reason));
    }
}
