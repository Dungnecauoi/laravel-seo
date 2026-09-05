<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SearchConsoleStatsController
{
    public function __invoke(Request $request): View
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

        return view('seo::panel.search-console', [
            'days' => $days,
            'rows' => $rows,
            'totalClicks' => (int) $rows->sum('clicks'),
            'totalImpressions' => (int) $rows->sum('impressions'),
        ]);
    }
}
