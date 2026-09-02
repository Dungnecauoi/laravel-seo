<?php

declare(strict_types=1);

namespace Duxbo\Seo\Events;

use Duxbo\Seo\Contracts\SitemapSource;
use Duxbo\Seo\Data\SitemapUrl;

/**
 * Fired for every URL before it is written.
 *
 * Mutable on purpose: a listener rewrites the URL or excludes it outright,
 * which is how a project applies a rule the source itself cannot express.
 */
final class SitemapUrlAdded
{
    public bool $excluded = false;

    public function __construct(
        public readonly SitemapSource $source,
        public SitemapUrl $url,
    ) {
    }

    public function exclude(): void
    {
        $this->excluded = true;
    }
}
