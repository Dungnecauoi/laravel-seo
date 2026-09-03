<?php

declare(strict_types=1);

namespace Duxbo\Seo\Locale;

use Duxbo\Seo\Contracts\HasAlternateLocales;
use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Contracts\Seoable;

/**
 * Decides which locales are worth an hreflang / sitemap alternate link.
 *
 * The single place every formatter and the sitemap now ask, instead of each
 * independently assuming a record exists in every globally supported locale
 * — the assumption that used to put a `hreflang="en"` link on a Vietnamese-
 * only page, pointing at a URL that 404s. Google does not just ignore that
 * one broken link; a broken hreflang URL can get the whole cluster discarded.
 *
 * Never guesses past what it is told. A model that knows its own translation
 * coverage implements {@see HasAlternateLocales} and is trusted outright.
 * Without that, the only locales considered are ones with their own stored
 * SEO row — a weaker signal, but at least one a person deliberately created,
 * rather than the full site-wide locale list assumed to apply to a record
 * that may only exist in one language.
 */
final class AlternateLocaleResolver
{
    public function __construct(
        private readonly MetadataRepository $repository,
        private readonly LocaleResolver $locales,
    ) {
    }

    /**
     * @param  string|null  $currentLocale  The locale actually being
     *   rendered right now, if there is one — always counted as known
     *   without needing evidence, since a page renders in it by definition.
     *   A formatter passes the page's current locale; the sitemap, which has
     *   no single "current" locale for a URL, passes null.
     * @return list<string>
     */
    public function resolve(?Seoable $model, ?string $currentLocale = null): array
    {
        if ($model === null) {
            return [];
        }

        $known = $model instanceof HasAlternateLocales
            ? $model->seoAlternateLocales()
            : $this->repository->locales($model);

        if ($currentLocale !== null) {
            $known[] = $currentLocale;
        }

        // Intersected even when the model is explicit about it: a locale the
        // site no longer supports should not resurface in a link just
        // because a record's own data was never updated.
        /** @var list<string> $result */
        $result = array_values(array_intersect(array_unique($known), $this->locales->supported()));

        return $result;
    }
}
