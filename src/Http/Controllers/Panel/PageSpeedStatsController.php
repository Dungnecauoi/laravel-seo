<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PageSpeedStatsController
{
    public function __invoke(Request $request): View
    {
        $table = (string) config('seo.pagespeed.table', 'seo_pagespeed_stats');
        $strategy = (string) $request->query('strategy', 'mobile');

        $latestIds = DB::table($table)
            ->where('strategy', $strategy)
            ->selectRaw('MAX(id) as id')
            ->groupBy('url_hash');

        $rows = DB::table($table)
            ->joinSub($latestIds, 'latest', "{$table}.id", '=', 'latest.id')
            ->orderByDesc('performance_score')
            ->limit(200)
            ->get();

        return view('seo::panel.pagespeed', ['rows' => $rows, 'strategy' => $strategy]);
    }
}
