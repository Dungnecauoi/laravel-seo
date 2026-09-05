<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read side of `php artisan seo:search-console:sync` — the last `--days`
 * worth of `seo_search_console_stats`, summed per page rather than left as
 * one row per page per day, which is the shape a UI actually wants ("which
 * pages get the most clicks"), not a spreadsheet of daily rows.
 */
final class SearchConsoleStatsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $table = (string) config('seo.search_console.table', 'seo_search_console_stats');

        $since = Carbon::now()->subDays($days)->toDateString();

        $rows = DB::table($table)
            ->where('date', '>=', $since)
            ->selectRaw('url, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->groupBy('url')
            ->orderByDesc('clicks')
            ->limit(200)
            ->get();

        $data = $rows->map(static fn (object $row): array => [
            'url' => (string) $row->url,
            'clicks' => (int) $row->clicks,
            'impressions' => (int) $row->impressions,
            'ctr' => $row->impressions > 0 ? round($row->clicks / $row->impressions, 4) : 0.0,
            'position' => $row->position !== null ? round((float) $row->position, 2) : null,
        ])->all();

        return $this->json([
            'days' => $days,
            'totalClicks' => array_sum(array_column($data, 'clicks')),
            'totalImpressions' => array_sum(array_column($data, 'impressions')),
            'data' => $data,
        ]);
    }
}
