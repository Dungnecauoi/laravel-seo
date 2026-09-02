<?php

declare(strict_types=1);

namespace Duxbo\Seo\Locale;

use Duxbo\Seo\Contracts\LocaleResolver;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Reads the locale from the framework, with config-declared fallbacks.
 *
 * Bind something else when the project already has its own translation
 * mechanism — the package deliberately requires no translation library.
 */
final class AppLocaleResolver implements LocaleResolver
{
    public function __construct(
        private readonly Application $app,
        private readonly Config $config,
    ) {
    }

    public function current(): ?string
    {
        $locale = $this->app->getLocale();

        return $locale !== '' ? $locale : null;
    }

    /**
     * Locales to try, in order, always ending at null.
     *
     * Null is the shared record that applies to every language, so it is the
     * last resort and is never omitted — otherwise a site with one language and
     * no locale column would resolve nothing at all.
     *
     * A null argument is taken literally: it asks for the shared record, not
     * for the current locale. Substituting the current locale here would make
     * `find($model, null)` mean something different from `save($model, …, null)`,
     * and callers would have no way to address the shared row on its own.
     * Resolution of "whatever locale we are in" happens above this, where the
     * context is built.
     *
     * @return list<string|null>
     */
    public function fallbackChain(?string $locale = null): array
    {
        if ($locale === null) {
            return [null];
        }

        /** @var array<string, list<string|null>> $configured */
        $configured = $this->config->get('seo.locales.fallbacks', []);

        $chain = [$locale];

        if (isset($configured[$locale])) {
            foreach ($configured[$locale] as $next) {
                $chain[] = $next;
            }
        } elseif (str_contains($locale, '-')) {
            // en-GB implies en, without anyone having to declare it.
            $chain[] = strtok($locale, '-') ?: null;
        }

        $chain[] = null;

        /** @var list<string|null> $unique */
        $unique = array_values(array_unique($chain, SORT_REGULAR));

        return $unique;
    }

    /**
     * @return list<string>
     */
    public function supported(): array
    {
        /** @var list<string> $supported */
        $supported = $this->config->get('seo.locales.supported', []);

        return $supported;
    }
}
