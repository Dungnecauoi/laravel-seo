<?php

declare(strict_types=1);

namespace Duxbo\Seo\Exceptions;

use RuntimeException;

final class GoogleIndexingSubmissionFailed extends RuntimeException implements SeoException
{
    public static function notConfigured(string $key): self
    {
        return new self(sprintf(
            'seo.google_indexing.enabled is true but %s is not configured. Create a service account in '
            .'Google Cloud Console, download its JSON key, add its client_email as an Owner on the Search '
            .'Console property (Settings → Users and permissions), then set client_email and private_key '
            .'from that JSON key.',
            $key,
        ));
    }

    public static function invalidPrivateKey(): self
    {
        return new self(
            'seo.google_indexing.private_key could not be used to sign a JWT — check it is the full PEM '
            .'key from the service account JSON, including the BEGIN/END lines.',
        );
    }

    public static function tokenRequestFailed(int $status, string $body): self
    {
        return new self(sprintf('Google token endpoint returned HTTP %d: %s', $status, mb_substr($body, 0, 500)));
    }
}
