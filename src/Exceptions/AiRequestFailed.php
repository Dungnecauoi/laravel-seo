<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class AiRequestFailed extends RuntimeException implements SeoException
{
    public static function noApiKey(string $driver): self
    {
        return new self(sprintf(
            'No API key configured for the [%s] SEO AI driver. Set it in config/seo.php '
            .'under seo.ai.drivers.%s.key, from an environment variable.',
            $driver,
            $driver,
        ));
    }

    public static function noModel(string $driver): self
    {
        return new self(sprintf(
            'No model configured for the [%s] SEO AI driver. Set seo.ai.drivers.%s.model — '
            .'model names change often, so the package does not hard-code one.',
            $driver,
            $driver,
        ));
    }

    public static function http(string $driver, int $status, string $body): self
    {
        return new self(sprintf('[%s] returned HTTP %d: %s', $driver, $status, $body));
    }

    public static function unreadable(string $driver): self
    {
        return new self(sprintf('[%s] returned a body that is not JSON.', $driver));
    }

    public static function notStructured(string $driver): self
    {
        return new self(sprintf(
            '[%s] did not return the structured object that was asked for. The driver '
            .'requests schema-constrained output; values are never scraped out of prose.',
            $driver,
        ));
    }

    public static function budgetExceeded(int $used, int $limit): self
    {
        return new self(sprintf(
            'The SEO AI daily token budget is spent: %d of %d used. Raise '
            .'seo.ai.daily_token_budget, or wait until tomorrow.',
            $used,
            $limit,
        ));
    }
}
