<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read side of `php artisan seo:broken-links` — currently-known-broken URLs
 * with which source records cite them, so a UI never re-runs the crawl just
 * to display what the last run already found.
 */
final class BrokenLinksController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $checksTable = (string) config('seo.broken_links.checks_table', 'seo_link_checks');
        $externalTable = (string) config('seo.broken_links.external_table', 'seo_external_links');
        $page = max(1, (int) $request->query('page', 1));

        $paginator = DB::table($checksTable)
            ->where('successful', false)
            ->orderByDesc('updated_at')
            ->paginate(20, page: $page);

        $data = collect($paginator->items())->map(function (object $row) use ($externalTable): array {
            $urlHash = md5((string) $row->url);

            $sources = DB::table($externalTable)
                ->where('target_hash', $urlHash)
                ->get(['source_type', 'source_id', 'anchor_text'])
                ->map(static fn (object $source): array => [
                    'sourceType' => $source->source_type,
                    'sourceId' => $source->source_id,
                    'anchorText' => $source->anchor_text,
                ])
                ->all();

            return [
                'url' => (string) $row->url,
                'statusCode' => $row->status_code !== null ? (int) $row->status_code : null,
                'error' => $row->error,
                'checkedAt' => $row->updated_at,
                'sources' => $sources,
            ];
        })->all();

        return $this->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
