<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read side of `php artisan seo:pagespeed` — the latest stored row per
 * URL+strategy, not a full history of every run, which is what a dashboard
 * table actually wants ("what does each page score right now").
 */
final class PageSpeedStatsController extends ApiController
{
    public function index(Request $request): JsonResponse
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

        $data = $rows->map(static fn (object $row): array => [
            'url' => (string) $row->url,
            'strategy' => (string) $row->strategy,
            'performanceScore' => $row->performance_score !== null ? (int) $row->performance_score : null,
            'lcpMs' => $row->lcp_ms !== null ? (int) $row->lcp_ms : null,
            'clsScore' => $row->cls_score !== null ? (float) $row->cls_score : null,
            'tbtMs' => $row->tbt_ms !== null ? (int) $row->tbt_ms : null,
            'fcpMs' => $row->fcp_ms !== null ? (int) $row->fcp_ms : null,
            'speedIndexMs' => $row->speed_index_ms !== null ? (int) $row->speed_index_ms : null,
            'fieldDataAvailable' => (bool) $row->field_data_available,
            'cwvCategory' => $row->cwv_category,
            'date' => $row->date,
        ])->all();

        return $this->json(['strategy' => $strategy, 'data' => $data]);
    }
}
