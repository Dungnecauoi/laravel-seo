<?php

declare(strict_types=1);

namespace Duxbo\Seo\Console;

use Duxbo\Seo\Exceptions\SearchConsoleSyncFailed;
use Duxbo\Seo\SearchConsole\SearchConsoleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SearchConsoleSyncCommand extends Command
{
    protected $signature = 'seo:search-console:sync
        {--days=7 : How many days of history to pull, ending 3 days ago}';

    protected $description = "Sync Search Console clicks, impressions and position for the site's own pages";

    public function handle(SearchConsoleClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('seo.search_console.enabled is false — nothing was synced.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));

        // Search Console's own numbers for a day keep shifting for roughly
        // 48 hours after it happens — ending the window 3 days back avoids
        // syncing a day's data before it has settled, only to sync the same
        // day again once it changes underneath a chart already drawn from it.
        $end = Carbon::now()->subDays(3);
        $start = $end->copy()->subDays($days - 1);

        try {
            $rows = $client->fetch($start->toDateString(), $end->toDateString());
        } catch (SearchConsoleSyncFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $table = (string) config('seo.search_console.table', 'seo_search_console_stats');
        $now = Carbon::now();

        foreach ($rows as $row) {
            $key = ['url_hash' => md5($row['page']), 'date' => $row['date']];

            $exists = DB::table($table)->where($key)->exists();

            DB::table($table)->updateOrInsert($key, [
                'url' => $row['page'],
                'clicks' => $row['clicks'],
                'impressions' => $row['impressions'],
                'ctr' => $row['ctr'],
                'position' => $row['position'],
                'updated_at' => $now,
                ...($exists ? [] : ['created_at' => $now]),
            ]);
        }

        $this->info(sprintf(
            '%d row(s) synced for %s to %s.',
            count($rows),
            $start->toDateString(),
            $end->toDateString(),
        ));

        return self::SUCCESS;
    }
}
