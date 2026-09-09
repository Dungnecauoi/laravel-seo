<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Read side of `php artisan seo:search-console:inspect` — the latest stored
 * row per URL, not a full history of every run, matching {@see
 * PageSpeedStatsController}'s shape.
 */
final class UrlInspectionsController extends ApiController
{
    public function index(): JsonResponse
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

        $data = $rows->map(static fn (object $row): array => [
            'url' => (string) $row->url,
            'verdict' => $row->verdict,
            'coverageState' => $row->coverage_state,
            'robotsTxtState' => $row->robots_txt_state,
            'indexingState' => $row->indexing_state,
            'pageFetchState' => $row->page_fetch_state,
            'googleCanonical' => $row->google_canonical,
            'userCanonical' => $row->user_canonical,
            'mobileUsabilityVerdict' => $row->mobile_usability_verdict,
            'mobileUsabilityIssues' => json_decode((string) $row->mobile_usability_issues, true) ?? [],
            'richResultsVerdict' => $row->rich_results_verdict,
            'date' => $row->date,
        ])->all();

        return $this->json(['data' => $data]);
    }
}
