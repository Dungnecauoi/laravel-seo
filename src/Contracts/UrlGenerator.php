<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * Produces public URLs for records and locales.
 *
 * Kept separate from `Seoable::seoUrl()` because sitemap and hreflang output
 * need a record's URL in a locale that is not the current one, which a model
 * method answering for "now" cannot provide.
 */
interface UrlGenerator
{
    public function forModel(Seoable $model, ?string $locale = null): string;

    /**
     * Same page in another locale, for `<link rel="alternate" hreflang>`.
     */
    public function alternate(string $url, string $locale): string;

    /**
     * Make a possibly relative URL absolute against the configured site root.
     */
    public function absolute(string $url): string;
}
