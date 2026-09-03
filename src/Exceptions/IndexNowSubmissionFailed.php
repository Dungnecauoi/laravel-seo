<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class IndexNowSubmissionFailed extends RuntimeException implements SeoException
{
    public static function noKey(): self
    {
        return new self(
            'seo.indexnow.enabled is true but no key is configured. Set seo.indexnow.key — any '
            .'random string works, e.g. `php -r "echo bin2hex(random_bytes(16));"` — and it must '
            .'stay the same between deploys, since it doubles as the file Bing checks to confirm '
            .'you own the site.',
        );
    }

    public static function http(int $status, string $body): self
    {
        return new self(sprintf('IndexNow returned HTTP %d: %s', $status, mb_substr($body, 0, 500)));
    }
}
