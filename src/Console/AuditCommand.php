<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Audit\Audit;
use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Seo;
use Duxbo\Seo\Support\ExternalSeoSignals;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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

    public function handle(Seo $seo, ExternalSeoSignals $signals): int
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

            $external = $signals->for($record->seoUrl(), $record);
            $pagespeedScore = $external['pagespeedScore'];
            $gscVerdict = $external['gscVerdict'];
            $brokenLinksCount = $external['brokenLinksCount'];

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
}
