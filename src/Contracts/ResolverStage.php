<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Closure;
use Duxbo\Seo\Data\SeoContext;

/**
 * One step in the chain that decides a page's final metadata.
 *
 * The fallback order is a configurable list of these rather than hard-coded
 * conditionals, which is what lets someone drop a stage, reorder two, or insert
 * their own — "generate with AI when empty", "read from an external CMS first"
 * — without touching package code.
 */
interface ResolverStage
{
    /**
     * @param  Closure(SeoContext): SeoContext  $next
     */
    public function handle(SeoContext $context, Closure $next): SeoContext;
}
