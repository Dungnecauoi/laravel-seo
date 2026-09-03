<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Seo;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * The thorough half of duplicate detection.
 *
 * The live check at save time ({@see \Duxbo\Seo\Http\Concerns\WarnsAboutDuplicates})
 * only compares *stored* title/description strings, because resolving every
 * other record through the pipeline does not belong on a request a save is
 * waiting on. This command can afford exactly that: it resolves every record
 * of a model — same fallback chain a real page render would use — and groups
 * by the result, catching the case the live check cannot: two untitled posts
 * that both inherit the same per-model template, and would still show
 * Google the identical title in two different search results.
 */
final class DuplicatesCommand extends Command
{
    protected $signature = 'seo:duplicates
        {model : Fully-qualified class name of the model to scan}
        {--locale= : Resolve titles in this locale}
        {--field=title : title, description, or both}';

    protected $description = 'Find records whose resolved title or description matches another record';

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

        $locale = $this->option('locale');
        $locale = is_string($locale) ? $locale : null;

        $field = $this->option('field');
        $fields = $field === 'both' ? ['title', 'description'] : [(string) $field];

        $this->info("Resolving {$modelClass}"." (this reads every record through the full fallback chain — it does not scale to millions of rows the way seo:sitemap does)");

        foreach ($fields as $checkField) {
            $this->reportDuplicates($modelClass, $checkField, $locale, $seo);
        }

        return self::SUCCESS;
    }

    private function reportDuplicates(string $modelClass, string $field, ?string $locale, Seo $seo): void
    {
        /** @var array<string, list<array{id: int|string, value: string}>> $groups */
        $groups = [];

        /** @var \Illuminate\Database\Eloquent\Builder<Model> $query */
        $query = $modelClass::query();

        foreach ($query->lazyById() as $record) {
            /** @var Model&Seoable $record */
            $data = $seo->for($record, $locale);
            $value = $field === 'description' ? $data->description : $data->title;

            if ($value === null || trim($value) === '') {
                continue;
            }

            // Grouped case-insensitively: two titles differing only by case
            // still show as the same result to a person scanning a SERP.
            $key = mb_strtolower(trim($value));
            $groups[$key][] = ['id' => $record->getKey(), 'value' => $value];
        }

        $duplicates = array_filter($groups, static fn (array $rows): bool => count($rows) > 1);

        if ($duplicates === []) {
            $this->info("No duplicate resolved {$field}s.");

            return;
        }

        $this->warn(count($duplicates)." duplicate resolved {$field}(s):");

        foreach ($duplicates as $rows) {
            $this->line('  "'.$rows[0]['value'].'"');

            foreach ($rows as $row) {
                $this->line('    #'.$row['id']);
            }
        }
    }
}
