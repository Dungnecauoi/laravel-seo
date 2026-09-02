<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

/**
 * Decides which locale the current page is in, and what to fall back to.
 *
 * Bind your own when the project already has a translation mechanism — the
 * package deliberately requires no particular translation library.
 */
interface LocaleResolver
{
    public function current(): ?string;

    /**
     * Locales to try, in order, ending with null for the shared record.
     *
     * `en-GB` typically yields `['en-GB', 'en', null]`.
     *
     * @return list<string|null>
     */
    public function fallbackChain(?string $locale = null): array;

    /**
     * Every locale the site publishes, for hreflang.
     *
     * @return list<string>
     */
    public function supported(): array;
}
