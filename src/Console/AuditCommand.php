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

/**
 * Scores every record of a model the same way `Seo::analyzeModel()` does for
 * one, and keeps the result — a live analysis in the panel or API answers
 * "how is this page doing right now"; this answers "is the site's SEO
 * getting better or worse", which needs a history nothing else here keeps.
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

        /** @var \Illuminate\Database\Eloquent\Builder<Model> $query */
        $query = $modelClass::query();

        foreach ($query->lazyById() as $record) {
            /** @var Model&Seoable $record */
            $content = (string) ($record->{$contentAttribute} ?? '');
            $report = $seo->analyzeModel($record, $content, $locale);

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
                'created_at' => Carbon::now(),
            ]);

            $scores[] = $report->score;
        }

        $batch->update([
            'total_records' => count($scores),
            'average_score' => $scores !== [] ? round(array_sum($scores) / count($scores), 2) : null,
            'min_score' => $scores !== [] ? min($scores) : null,
            'max_score' => $scores !== [] ? max($scores) : null,
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

        return self::SUCCESS;
    }
}
