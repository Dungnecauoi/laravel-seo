<?php

declare(strict_types=1);

namespace Duxbo\Seo\Events;

use Duxbo\Seo\Contracts\Seoable;

/**
 * Fired after {@see \Duxbo\Seo\Seo::save()} writes.
 *
 * Nothing in this package listens to its own event — a listener that called
 * {@see \Duxbo\Seo\IndexNow\IndexNowSubmitter} on every save would mean a
 * blocking outbound request on every panel save, for every project, whether
 * or not IndexNow is even relevant to it. This is the extension point for an
 * application that wants that: queue a job here, submit to IndexNow, purge a
 * cache, whatever a save should actually trigger for that project.
 *
 * `url` is null when the model's route cannot be resolved (no
 * `seo.models.{class}.route` mapping yet, typically) — saving metadata must
 * never fail because of that; it is an unrelated concern from storing what
 * was typed in. A listener that needs the URL should treat a null one as
 * "skip this record" rather than assume {@see Seoable::seoUrl()} would
 * succeed either.
 */
final class SeoMetaSaved
{
    public function __construct(
        public readonly Seoable $model,
        public readonly ?string $locale,
        public readonly ?string $url,
    ) {
    }
}
