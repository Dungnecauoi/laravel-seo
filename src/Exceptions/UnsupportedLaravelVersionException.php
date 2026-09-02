<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

/**
 * Thrown during boot when the host framework is outside the supported range.
 *
 * Failing here, with an actionable message, beats an obscure error deep in a
 * request three weeks later.
 */
final class UnsupportedLaravelVersionException extends RuntimeException implements SeoException
{
    public static function make(string $running, int $min, int $max): self
    {
        return new self(sprintf(
            'duxbo/laravel-seo 1.x supports Laravel %d-%d, but Laravel %s is running. '
            .'Install a matching release instead: composer require duxbo/laravel-seo:^2.0',
            $min,
            $max,
            $running,
        ));
    }
}
