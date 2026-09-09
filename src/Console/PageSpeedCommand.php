<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Exceptions\PageSpeedFetchFailed;
use Duxbo\Seo\PageSpeed\PageSpeedClient;
use Illuminate\Console\Command;

/**
 * Manual or scripted run — `php artisan seo:pagespeed https://example.com/`
 * after a deploy, or on a schedule for the handful of pages worth tracking.
 * Unlike Search Console, PageSpeed Insights has no "give me every page"
 * endpoint — it only ever scores the URL(s) it is explicitly asked to,
 * mirroring the explicit-URL shape of {@see IndexNowCommand} and {@see
 * GoogleIndexingCommand} rather than {@see SearchConsoleSyncCommand}'s pull.
 */
final class PageSpeedCommand extends Command
{
    protected $signature = 'seo:pagespeed
        {urls* : Absolute URLs to test}
        {--strategy=mobile : mobile or desktop}';

    protected $description = 'Score URL(s) with PageSpeed Insights and store the result for trend tracking';

    public function handle(PageSpeedClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('seo.pagespeed.enabled is false — nothing was checked.');

            return self::FAILURE;
        }

        /** @var list<string> $urls */
        $urls = $this->argument('urls');
        $strategy = (string) $this->option('strategy');

        $failed = false;

        foreach ($urls as $url) {
            try {
                $result = $client->check($url, $strategy);
            } catch (PageSpeedFetchFailed $e) {
                $this->error("{$url}: {$e->getMessage()}");
                $failed = true;

                continue;
            }

            $this->info(sprintf('%s: performance %s/100', $url, $result['performanceScore'] ?? '—'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
