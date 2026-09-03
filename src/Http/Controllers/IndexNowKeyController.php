<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers;

use Duxbo\Seo\IndexNow\IndexNowSubmitter;
use Illuminate\Http\Response;

/**
 * Serves the IndexNow key file — the protocol's proof that whoever is
 * submitting URLs actually controls this domain. Registered only at the
 * exact `{key}.txt` path, not a wildcard, so it never shadows an unrelated
 * route the way a catch-all would.
 */
final class IndexNowKeyController
{
    public function __construct(private readonly IndexNowSubmitter $submitter)
    {
    }

    public function __invoke(): Response
    {
        return new Response((string) $this->submitter->key(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
