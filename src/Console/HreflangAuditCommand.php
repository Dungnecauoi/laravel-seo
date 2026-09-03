<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Locale\AlternateLocaleResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Checks every record's claimed hreflang alternates for URLs that collide
 * across locales.
 *
 * This package's hreflang alternates are not separate records the way a
 * typical multi-model translation setup has them (a Vietnamese post row and
 * an English post row, linked, each needing to reference the other back) —
 * every alternate here comes from {@see UrlGenerator::alternate()} on the
 * *same* record's own URL, just asked for in a different locale (exactly
 * what every formatter already does), so the classic "hreflang isn't
 * reciprocal" bug cannot occur by construction: there is only ever one
 * record generating both directions of the link.
 *
 * What can still go wrong is the URL generator itself: a custom
 * `seo.locales.alternate_url` resolver that ignores its `$locale` argument,
 * or the default locale-segment convention colliding with a path the site
 * already uses, produces the identical URL for two different `hreflang`
 * values — Google then sees two language declarations on what looks like one
 * page, which is its own way of getting the whole hreflang cluster discarded.
 */
final class HreflangAuditCommand extends Command
{
    protected $signature = 'seo:hreflang
        {model : Fully-qualified class name of the model to scan}';

    protected $description = "Find records whose hreflang alternates resolve to the same URL for two different locales";

    public function handle(AlternateLocaleResolver $alternateLocales, UrlGenerator $urls): int
    {
        /** @var class-string $modelClass */
        $modelClass = $this->argument('model');

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            $this->error("No model class [{$modelClass}].");

            return self::FAILURE;
        }

        $probe = new $modelClass();

        if (! $probe instanceof Seoable) {
            $this->error("[{$modelClass}] does not implement Seoable.");

            return self::FAILURE;
        }

        $flagged = 0;
        $checked = 0;

        /** @var \Illuminate\Database\Eloquent\Builder<Model> $query */
        $query = $modelClass::query();

        foreach ($query->lazyById() as $record) {
            /** @var Model&Seoable $record */
            $locales = $alternateLocales->resolve($record);

            if (count($locales) < 2) {
                continue;
            }

            $checked++;

            /** @var array<string, string> $seenAt */
            $seenAt = [];

            $ownUrl = $record->seoUrl();

            foreach ($locales as $locale) {
                $url = $urls->alternate($ownUrl, $locale);

                if (isset($seenAt[$url])) {
                    $this->warn(sprintf(
                        '#%s: hreflang="%s" and hreflang="%s" both resolve to %s',
                        $record->getKey(),
                        $seenAt[$url],
                        $locale,
                        $url,
                    ));
                    $flagged++;

                    continue;
                }

                $seenAt[$url] = $locale;
            }
        }

        $this->info("Checked {$checked} record(s) with 2 or more alternates.");

        if ($flagged === 0) {
            $this->info('No colliding hreflang URLs found.');
        } else {
            $this->warn("{$flagged} colliding pair(s) found — see above.");
        }

        return self::SUCCESS;
    }
}
