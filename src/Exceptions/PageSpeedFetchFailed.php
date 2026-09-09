<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class PageSpeedFetchFailed extends RuntimeException implements SeoException
{
    public static function notConfigured(string $key): self
    {
        return new self(sprintf(
            'seo.pagespeed.enabled is true but %s is not configured. Enable the "PageSpeed Insights API" '
            .'in Google Cloud Console, create an API key under Credentials, and set it there.',
            $key,
        ));
    }

    public static function http(int $status, string $body): self
    {
        return new self(sprintf('PageSpeed Insights returned HTTP %d: %s', $status, mb_substr($body, 0, 500)));
    }
}
