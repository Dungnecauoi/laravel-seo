<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class IndexNowLogController
{
    public function __invoke(): View
    {
        $table = (string) config('seo.indexnow.log_table', 'seo_indexnow_log');

        $paginator = DB::table($table)->latest('id')->paginate(30);

        return view('seo::panel.indexnow-log', ['paginator' => $paginator]);
    }
}
