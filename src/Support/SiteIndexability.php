<?php

declare(strict_types=1);

namespace Duxbo\Seo\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Whether the site as a whole should be crawled and indexed right now.
 *
 * False when `seo.enabled` is off — the safety net for a demo domain shown to
 * a client before launch, where the alternative is forgetting one flag and
 * having Google index throwaway content under a real client's name — or when
 * the current environment is outside `seo.indexable_environments`.
 *
 * Both robots.txt and the sitemap check this the same shared class, so
 * neither can end up telling a crawler something the other contradicts —
 * exactly the "Disallow: /" here, "here are the URLs" there mismatch that
 * gets a site flagged in Search Console.
 *
 * Deliberately not what {@see \Duxbo\Seo\Resolution\Stages\GlobalDefaultStage}
 * uses for its own environment check: that one only supplies a *default*
 * robots value which a stored per-page override is allowed to beat — the
 * right, weaker behaviour for one page. This class answers a site-wide
 * question with no such escape hatch, because `seo.enabled = false` is
 * supposed to mean "not this domain, full stop" regardless of what a content
 * editor set on an individual record.
 */
final class SiteIndexability
{
    public function __construct(
        private readonly Config $config,
        private readonly Application $app,
    ) {
    }

    public function ok(): bool
    {
        if ($this->config->get('seo.enabled', true) !== true) {
            return false;
        }

        $environments = $this->config->get('seo.indexable_environments', ['production']);

        if (! is_array($environments)) {
            return true;
        }

        /** @var list<string> $environments */
        return $this->app->environment($environments);
    }
}
