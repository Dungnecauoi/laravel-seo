<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class BrokenLinksController
{
    public function __invoke(): View
    {
        $checksTable = (string) config('seo.broken_links.checks_table', 'seo_link_checks');
        $externalTable = (string) config('seo.broken_links.external_table', 'seo_external_links');

        $paginator = DB::table($checksTable)
            ->where('successful', false)
            ->orderByDesc('updated_at')
            ->paginate(30);

        $sourcesByHash = [];

        foreach ($paginator->items() as $row) {
            $hash = md5((string) $row->url);
            $sourcesByHash[$hash] = DB::table($externalTable)->where('target_hash', $hash)->count();
        }

        return view('seo::panel.broken-links', ['paginator' => $paginator, 'sourcesByHash' => $sourcesByHash]);
    }
}
