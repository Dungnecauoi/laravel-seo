<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class SearchConsoleSyncFailed extends RuntimeException implements SeoException
{
    public static function notConfigured(string $missing): self
    {
        return new self(sprintf(
            'seo.search_console.enabled is true but %s is not set. All of client_id, '
            .'client_secret, refresh_token and site_url are required — see config/seo.php '
            .'for how to obtain them.',
            $missing,
        ));
    }

    public static function tokenRefreshFailed(int $status, string $body): self
    {
        return new self(sprintf(
            'Could not refresh a Google access token (HTTP %d): %s. The refresh token may '
            .'have been revoked — sending the OAuth client through the consent screen again '
            .'produces a new one.',
            $status,
            mb_substr($body, 0, 500),
        ));
    }

    public static function http(int $status, string $body): self
    {
        return new self(sprintf('Search Console returned HTTP %d: %s', $status, mb_substr($body, 0, 500)));
    }
}
