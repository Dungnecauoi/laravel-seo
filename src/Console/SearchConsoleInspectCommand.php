<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Exceptions\SearchConsoleSyncFailed;
use Duxbo\Seo\SearchConsole\SearchConsoleClient;
use Illuminate\Console\Command;

/**
 * Manual or scripted run — `php artisan seo:search-console:inspect
 * https://example.com/bai-viet` for the handful of pages worth checking.
 * Unlike {@see SearchConsoleSyncCommand}'s pull of every page's performance,
 * URL Inspection has no "give me every page" endpoint and Google rate-limits
 * it far tighter (roughly 2,000 requests/day per site) — explicit URLs only,
 * mirroring {@see PageSpeedCommand}'s shape rather than the sync command's.
 */
final class SearchConsoleInspectCommand extends Command
{
    protected $signature = 'seo:search-console:inspect {urls* : Absolute URLs to inspect}';

    protected $description = "Check whether Google has URL(s) indexed, and why not if it doesn't";

    public function handle(SearchConsoleClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('seo.search_console.enabled is false — nothing was inspected.');

            return self::FAILURE;
        }

        /** @var list<string> $urls */
        $urls = $this->argument('urls');
        $failed = false;

        foreach ($urls as $url) {
            try {
                $result = $client->inspect($url);
            } catch (SearchConsoleSyncFailed $e) {
                $this->error("{$url}: {$e->getMessage()}");
                $failed = true;

                continue;
            }

            $this->info(sprintf(
                '%s: %s (%s)',
                $url,
                $result['coverageState'] ?? 'unknown',
                $result['verdict'] ?? 'NEUTRAL',
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
