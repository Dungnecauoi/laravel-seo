<?php

declare(strict_types=1);

namespace Duxbo\Seo\Events;

use Duxbo\Seo\Data\NotFoundHit;

/**
 * Fired after a 404 is recorded — for alerting when broken links spike.
 *
 * `$hit` carries only path/referrer/user agent, not a full `Request` — a
 * hit logged through the ingest endpoint never had one to begin with.
 */
final class NotFoundLogged
{
    public function __construct(
        public readonly string $path,
        public readonly NotFoundHit $hit,
    ) {
    }
}
