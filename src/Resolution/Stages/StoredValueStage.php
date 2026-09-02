<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Stages;

use Closure;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\SeoContext;

/**
 * What someone actually typed into the SEO panel. Highest priority.
 */
final class StoredValueStage implements ResolverStage
{
    public function __construct(private readonly MetadataRepository $repository)
    {
    }

    public function handle(SeoContext $context, Closure $next): SeoContext
    {
        if (! $context->hasModel()) {
            return $next($context);
        }

        // Already loaded by a `withSeo()` eager load, when one ran — this is
        // what keeps an index page from issuing one query per record.
        $stored = $context->get('stored_data')
            ?? $this->repository->find($context->model, $context->locale);

        if ($stored !== null) {
            $context = $context->fillMissing($stored);
        }

        return $next($context);
    }
}
