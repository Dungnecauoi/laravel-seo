<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Audit\Audit;
use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Seo;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Scores every record of a model the same way `Seo::analyzeModel()` does for
 * one, and keeps the result — a live analysis in the panel or API answers
 * "how is this page doing right now"; this answers "is the site's SEO
 * getting better or worse", which needs a history nothing else here keeps.
 *
 * Content score is the only number this command computes itself. PageSpeed,
 * indexing status and broken-link count are *joined in* from whatever
 * `seo:pagespeed` / `seo:search-console:inspect` / `seo:broken-links` last
 * stored for that record's URL — never fetched live here, since a batch
 * over every record of a model would blow through Google's own rate limits
 * on those two APIs almost immediately. A record with no such data leaves
 * the corresponding column null, not a misleadingly cheerful zero.
 *
 * Deliberately not scheduled by this package itself: scoring readability and
 * keyword usage needs the record's actual body content, and only the
 * application knows which attribute holds that — the same reason
 * `seo.models.*.route` exists for URLs rather than this package guessing a
 * column name.
 */
final class AuditCommand extends Command
{
    protected $signature = 'seo:audit
        {model : Fully-qualified class name of the model to scan}
        {--content=body : The model attribute holding page content to analyse}
        {--locale= : Resolve and analyse in this locale}';

    protected $description = 'Score every record of a model and store the result as a new audit batch';

    public function handle(Seo $seo): int
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

        $contentAttribute = (string) $this->option('content');
        $locale = $this->option('locale');
        $locale = is_string($locale) ? $locale : null;

        $batch = AuditBatch::query()->create([
            'model' => $modelClass,
            'locale' => $locale,
            'started_at' => Carbon::now(),
        ]);

        $this->info("Batch #{$batch->id}: scoring {$modelClass} using the [{$contentAttribute}] attribute for content…");

        $scores = [];
        $pagespeedScores = [];
        $notIndexedCount = 0;
        $hasGscData = false;
        $withBrokenLinksCount = 0;
        $hasBrokenLinksData = false;

        /** @var \Illuminate\Database\Eloquent\Builder<Model> $query */
        $query = $modelClass::query();

        foreach ($query->lazyById() as $record) {
            /** @var Model&Seoable $record */
            $content = (string) ($record->{$contentAttribute} ?? '');
            $report = $seo->analyzeModel($record, $content, $locale);

            $pagespeedScore = $this->latestPagespeedScore($record->seoUrl());
            $gscVerdict = $this->latestGscVerdict($record->seoUrl());
            $brokenLinksCount = $this->brokenLinksCount($record);

            Audit::query()->create([
                'batch_id' => $batch->id,
                'seoable_type' => $record->seoType(),
                'seoable_id' => (string) $record->seoKey(),
                'locale' => $locale,
                'score' => $report->score,
                'failed_checks' => array_values(array_map(
                    static fn ($result): string => $result->id,
                    $report->problems(),
                )),
                'pagespeed_score' => $pagespeedScore,
                'gsc_verdict' => $gscVerdict,
                'broken_links_count' => $brokenLinksCount,
                'created_at' => Carbon::now(),
            ]);

            $scores[] = $report->score;

            if ($pagespeedScore !== null) {
                $pagespeedScores[] = $pagespeedScore;
            }

            if ($gscVerdict !== null) {
                $hasGscData = true;

                if ($gscVerdict !== 'PASS') {
                    $notIndexedCount++;
                }
            }

            if ($brokenLinksCount !== null) {
                $hasBrokenLinksData = true;

                if ($brokenLinksCount > 0) {
                    $withBrokenLinksCount++;
                }
            }
        }

        $batch->update([
            'total_records' => count($scores),
            'average_score' => $scores !== [] ? round(array_sum($scores) / count($scores), 2) : null,
            'min_score' => $scores !== [] ? min($scores) : null,
            'max_score' => $scores !== [] ? max($scores) : null,
            'average_pagespeed_score' => $pagespeedScores !== []
                ? round(array_sum($pagespeedScores) / count($pagespeedScores), 2)
                : null,
            'records_not_indexed' => $hasGscData ? $notIndexedCount : null,
            'records_with_broken_links' => $hasBrokenLinksData ? $withBrokenLinksCount : null,
            'finished_at' => Carbon::now(),
        ]);

        if ($scores === []) {
            $this->info("Batch #{$batch->id}: no records found.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Batch #%d: %d record(s), average score %s (min %d, max %d).',
            $batch->id,
            count($scores),
            $batch->average_score,
            $batch->min_score,
            $batch->max_score,
        ));

        if ($pagespeedScores !== []) {
            $this->info(sprintf(
                'Average PageSpeed: %s (%d/%d record(s) had data).',
                round(array_sum($pagespeedScores) / count($pagespeedScores), 1),
                count($pagespeedScores),
                count($scores),
            ));
        }

        if ($hasGscData) {
            $this->info("{$notIndexedCount} record(s) not passing Search Console's own index check.");
        }

        if ($hasBrokenLinksData) {
            $this->info("{$withBrokenLinksCount} record(s) cite at least one currently-broken external link.");
        }

        return self::SUCCESS;
    }

    /**
     * The most recent stored mobile PageSpeed score for a URL — null if
     * `seo:pagespeed` has never been run against it.
     */
    private function latestPagespeedScore(string $url): ?int
    {
        $table = (string) config('seo.pagespeed.table', 'seo_pagespeed_stats');

        $score = DB::table($table)
            ->where('url_hash', md5($url))
            ->where('strategy', 'mobile')
            ->orderByDesc('date')
            ->value('performance_score');

        return $score !== null ? (int) $score : null;
    }

    /**
     * The most recent stored Search Console URL Inspection verdict for a
     * URL — null if `seo:search-console:inspect` has never been run
     * against it.
     */
    private function latestGscVerdict(string $url): ?string
    {
        $table = (string) config('seo.search_console.inspections_table', 'seo_url_inspections');

        $verdict = DB::table($table)
            ->where('url_hash', md5($url))
            ->orderByDesc('date')
            ->value('verdict');

        return is_string($verdict) && $verdict !== '' ? $verdict : null;
    }

    /**
     * How many of this record's own external links are currently known
     * broken — null if `seo:broken-links` has never crawled this record
     * (indistinguishable, by design of that command's own storage, from a
     * record that was crawled and simply has no external links at all).
     */
    private function brokenLinksCount(Seoable $record): ?int
    {
        $externalTable = (string) config('seo.broken_links.external_table', 'seo_external_links');
        $checksTable = (string) config('seo.broken_links.checks_table', 'seo_link_checks');

        $sourceType = $record->seoType();
        $sourceId = (string) $record->seoKey();

        $crawled = DB::table($externalTable)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();

        if (! $crawled) {
            return null;
        }

        return DB::table($externalTable)
            ->join($checksTable, "{$externalTable}.target_hash", '=', "{$checksTable}.url_hash")
            ->where("{$externalTable}.source_type", $sourceType)
            ->where("{$externalTable}.source_id", $sourceId)
            ->where("{$checksTable}.successful", false)
            ->count();
    }
}
