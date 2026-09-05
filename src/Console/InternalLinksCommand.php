<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Contracts\ContentExtractor;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\UrlGenerator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crawls one model's own content for internal links, and reports which of
 * its own pages nothing in that set links to.
 *
 * Scoped to one model rather than a true site-wide graph across every
 * exposed type — a blog with a hundred posts that never link to each other
 * is the case this catches, without needing every model in the application
 * registered here the way `seo.api.models` is for the REST API. Every crawl
 * of one record replaces its rows outright: simpler than diffing, and
 * correct even when a link's anchor text changed since the last run.
 */
final class InternalLinksCommand extends Command
{
    protected $signature = 'seo:internal-links
        {model : Fully-qualified class name of the model to scan}
        {--content=body : The model attribute holding page content to search for links}';

    protected $description = "Crawl a model's content for internal links and report pages nothing links to";

    public function handle(ContentExtractor $extractor, UrlGenerator $urls): int
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
        $table = (string) config('seo.internal_links.table', 'seo_internal_links');

        /** @var array<string, string> $ownUrls */
        $ownUrls = [];
        $linkCount = 0;
        $recordCount = 0;

        /** @var \Illuminate\Database\Eloquent\Builder<Model> $query */
        $query = $modelClass::query();

        foreach ($query->lazyById() as $record) {
            /** @var Model&Seoable $record */
            $recordCount++;
            $sourceType = $record->seoType();
            $sourceId = (string) $record->seoKey();

            $ownUrls[$sourceType.':'.$sourceId] = [
                'url' => $record->seoUrl(),
                'path' => self::path($record->seoUrl()),
            ];

            $content = (string) ($record->{$contentAttribute} ?? '');
            $links = $extractor->extract($content)->internalLinks();

            DB::table($table)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->delete();

            if ($links === []) {
                continue;
            }

            $rows = [];

            foreach ($links as $link) {
                $target = $urls->absolute($link->href);

                $rows[] = [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'target_url' => $target,
                    // Hashed on the path alone, not the full absolute URL: an
                    // href made absolute against app.url and a model's own
                    // seoUrl() can legitimately disagree on scheme or host
                    // (a CDN domain, a proxy, app.url simply not matching a
                    // model's own override) while still being the same page.
                    'target_hash' => md5(self::path($target)),
                    'anchor_text' => $link->text !== '' ? $link->text : null,
                    'created_at' => Carbon::now(),
                ];
            }

            DB::table($table)->insert($rows);
            $linkCount += count($rows);
        }

        $this->info("Crawled {$recordCount} record(s), found {$linkCount} internal link(s).");

        $this->reportOrphans($table, $ownUrls);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{url: string, path: string}>  $ownUrls  "{type}:{id}" => its own URL and path
     */
    private function reportOrphans(string $table, array $ownUrls): void
    {
        if ($ownUrls === []) {
            return;
        }

        /** @var array<string, true> $linkedPaths */
        $linkedPaths = [];

        foreach (DB::table($table)->distinct()->pluck('target_hash') as $hash) {
            $linkedPaths[$hash] = true;
        }

        $orphans = array_filter(
            $ownUrls,
            static fn (array $own): bool => ! isset($linkedPaths[md5($own['path'])]),
        );

        if ($orphans === []) {
            $this->info('No orphans — every page is linked to by at least one other in this set.');

            return;
        }

        $this->warn(count($orphans).' orphan page(s) — nothing in this set links to them:');

        foreach ($orphans as $own) {
            $this->line("  {$own['url']}");
        }
    }

    /**
     * The path alone, trailing slash trimmed except for the root — the part
     * of a URL that identifies "which page" independent of which scheme or
     * host it happened to be made absolute against.
     */
    private static function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
