<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class AiCircuitOpen extends RuntimeException implements SeoException
{
    public static function forDriver(string $driver, int $openUntil): self
    {
        $seconds = max(1, $openUntil - time());

        return new self(sprintf(
            'The AI driver [%s] has failed repeatedly and is paused for another %d second(s). '
            .'Raising seo.ai.circuit_breaker.threshold makes it trip less easily; the failing '
            .'requests are logged in seo_ai_log.',
            $driver,
            $seconds,
        ));
    }
}
