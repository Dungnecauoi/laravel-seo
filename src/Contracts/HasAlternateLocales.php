<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * A model that knows which locales it has real, reachable content in.
 *
 * Optional. Without it, hreflang and sitemap alternates fall back to a much
 * weaker signal — only locales with their own stored SEO row — rather than
 * assuming every record exists in every locale the site supports. That
 * assumption is exactly how a partially-translated site ends up emitting
 * `hreflang="en"` links to pages that 404: Google does not merely ignore the
 * broken link, it can discard the entire hreflang cluster over it.
 *
 * Implement this once a model's translation coverage is something the
 * application already knows — a translations table, a `spatie/laravel-
 * translatable` column, a `LOWER('locale') IN (...)` lookup — rather than
 * something this package could ever infer on its own.
 */
interface HasAlternateLocales
{
    /**
     * @return list<string>
     */
    public function seoAlternateLocales(): array;
}
