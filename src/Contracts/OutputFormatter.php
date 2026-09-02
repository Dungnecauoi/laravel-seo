<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\SeoContext;

/**
 * Renders resolved metadata in the shape the consuming framework expects.
 *
 * This is what makes the package fit any front end: the same SeoContext becomes
 * Blade meta tags, a Next.js `metadata` object, or a Nuxt `useHead()` payload,
 * so the front end maps nothing by hand.
 */
interface OutputFormatter
{
    /**
     * Name used to select this formatter, e.g. `next` for `?format=next`.
     */
    public function name(): string;

    /**
     * A string for markup formatters; an array for structured ones.
     */
    public function format(SeoContext $context): mixed;
}
