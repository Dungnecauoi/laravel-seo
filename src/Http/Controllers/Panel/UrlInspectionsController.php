<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class UrlInspectionsController
{
    public function __invoke(): View
    {
        $table = (string) config('seo.search_console.inspections_table', 'seo_url_inspections');

        $latestIds = DB::table($table)
            ->selectRaw('MAX(id) as id')
            ->groupBy('url_hash');

        $rows = DB::table($table)
            ->joinSub($latestIds, 'latest', "{$table}.id", '=', 'latest.id')
            ->orderBy('url')
            ->limit(200)
            ->get();

        return view('seo::panel.url-inspections', ['rows' => $rows]);
    }
}
