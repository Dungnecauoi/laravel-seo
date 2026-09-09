<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class GoogleIndexingLogController
{
    public function __invoke(): View
    {
        $table = (string) config('seo.google_indexing.log_table', 'seo_google_indexing_log');

        $paginator = DB::table($table)->latest('id')->paginate(30);

        return view('seo::panel.google-indexing-log', ['paginator' => $paginator]);
    }
}
