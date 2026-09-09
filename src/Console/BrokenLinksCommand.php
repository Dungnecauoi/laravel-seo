<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\BrokenLinks\BrokenLinkChecker;
use Duxbo\Seo\Contracts\ContentExtractor;
use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crawls one model's content for external links, then checks each distinct
 * target URL once — the same URL cited from ten different records is
 * checked once, not ten times, the reasoning behind splitting storage into
 * `seo_external_links` (who links to what) and `seo_link_checks` (is that
 * URL still good) rather than one flat table.
 *
 * Real outbound HTTP requests, one per distinct external URL found — the
 * same "no cost control" caveat every real-network command in this package
 * already carries. Meant for a scheduled run against a site's own handful
 * to low hundreds of external links, not a first pass over an unbounded
 * one — nothing here queues or parallelizes the checks.
 */
final class BrokenLinksCommand extends Command
{
    protected $signature = 'seo:broken-links
        {model : Fully-qualified class name of the model to scan}
        {--content=body : The model attribute holding page content to search for links}
        {--check=1 : Whether to also check each URL found (set 0 to only crawl)}';

    protected $description = "Crawl a model's content for external links and check which are broken";

    public function handle(ContentExtractor $extractor, BrokenLinkChecker $checker): int
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
        $table = (string) config('seo.broken_links.external_table', 'seo_external_links');

        /** @var array<string, true> $targets */
        $targets = [];
        $linkCount = 0;
        $recordCount = 0;

        /** @var \Illuminate\Database\Eloquent\Builder<Model> $query */
        $query = $modelClass::query();

        foreach ($query->lazyById() as $record) {
            /** @var Model&Seoable $record */
            $recordCount++;
            $sourceType = $record->seoType();
            $sourceId = (string) $record->seoKey();

            $content = (string) ($record->{$contentAttribute} ?? '');
            $links = $extractor->extract($content)->externalLinks();

            DB::table($table)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->delete();

            if ($links === []) {
                continue;
            }

            $rows = [];

            foreach ($links as $link) {
                $targets[$link->href] = true;

                $rows[] = [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'target_url' => $link->href,
                    'target_hash' => md5($link->href),
                    'anchor_text' => $link->text !== '' ? $link->text : null,
                    'created_at' => Carbon::now(),
                ];
            }

            DB::table($table)->insert($rows);
            $linkCount += count($rows);
        }

        $this->info("Crawled {$recordCount} record(s), found {$linkCount} external link(s), ".count($targets).' unique URL(s).');

        if ($this->option('check') === '0' || $targets === []) {
            return self::SUCCESS;
        }

        $this->checkTargets($checker, array_keys($targets));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $urls
     */
    private function checkTargets(BrokenLinkChecker $checker, array $urls): void
    {
        $checksTable = (string) config('seo.broken_links.checks_table', 'seo_link_checks');
        $now = Carbon::now();
        $broken = 0;

        $bar = $this->output->createProgressBar(count($urls));
        $bar->start();

        foreach ($urls as $url) {
            $result = $checker->check($url);

            if (! $result['successful']) {
                $broken++;
            }

            $key = ['url_hash' => md5($url)];
            $exists = DB::table($checksTable)->where($key)->exists();

            DB::table($checksTable)->updateOrInsert($key, [
                'url' => $url,
                'successful' => $result['successful'],
                'status_code' => $result['statusCode'],
                'error' => $result['error'],
                'updated_at' => $now,
                ...($exists ? [] : ['created_at' => $now]),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $broken > 0
            ? $this->warn("{$broken} broken link(s) found.")
            : $this->info('No broken links found.');
    }
}
