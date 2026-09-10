<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * The three pieces of a 404 hit {@see \Duxbo\Seo\NotFound\NotFoundLogger}
 * actually needs — decoupled from `Illuminate\Http\Request` so a hit can
 * come from this application's own router
 * ({@see \Duxbo\Seo\Http\Middleware\HandleNotFound}, which already has a
 * real request) or from a POST body a separately-deployed front end sends
 * when *its own* router 404s instead
 * ({@see \Duxbo\Seo\Http\Api\V1\NotFoundIngestController}).
 */
final class NotFoundHit
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $referrer = null,
        public readonly ?string $userAgent = null,
    ) {
    }
}
