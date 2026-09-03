<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Concerns;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Data\DuplicateMatch;
use Duxbo\Seo\Seo;

/**
 * Shared by the REST API and the Blade panel so a save through either surface
 * gets the same Yoast/Rank Math-style "this title is already used" nudge.
 */
trait WarnsAboutDuplicates
{
    /**
     * Advisory only: the save this runs after has already happened, and
     * nothing here blocks it.
     *
     * @param  array<string, mixed>  $dotted
     * @return array<string, list<array<string, mixed>>>
     */
    private function duplicateWarnings(Seo $seo, Seoable $model, array $dotted, ?string $locale): array
    {
        $warnings = [];

        if (is_string($dotted['title'] ?? null) && $dotted['title'] !== '') {
            $matches = $seo->duplicateTitles($model, $dotted['title'], $locale);

            if ($matches !== []) {
                $warnings['duplicate_title'] = $this->toArrayList($matches);
            }
        }

        if (is_string($dotted['description'] ?? null) && $dotted['description'] !== '') {
            $matches = $seo->duplicateDescriptions($model, $dotted['description'], $locale);

            if ($matches !== []) {
                $warnings['duplicate_description'] = $this->toArrayList($matches);
            }
        }

        return $warnings;
    }

    /**
     * @param  list<DuplicateMatch>  $matches
     * @return list<array<string, mixed>>
     */
    private function toArrayList(array $matches): array
    {
        return array_map(static fn (DuplicateMatch $m): array => $m->toArray(), $matches);
    }
}
