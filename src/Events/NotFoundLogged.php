<?php

declare(strict_types=1);

namespace Duxbo\Seo\Events;

use Illuminate\Http\Request;

/**
 * Fired after a 404 is recorded — for alerting when broken links spike.
 */
final class NotFoundLogged
{
    public function __construct(
        public readonly string $path,
        public readonly Request $request,
    ) {
    }
}
