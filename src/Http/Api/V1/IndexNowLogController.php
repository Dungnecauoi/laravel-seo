<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read side of {@see \Duxbo\Seo\IndexNow\IndexNowSubmitter}'s own logging —
 * "did this submission actually go through" without scrolling back through
 * console output.
 */
final class IndexNowLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $table = (string) config('seo.indexnow.log_table', 'seo_indexnow_log');
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $rows = DB::table($table)->latest('id')->limit($limit)->get();

        $data = $rows->map(static fn (object $row): array => [
            'id' => $row->id,
            'urls' => json_decode((string) $row->urls, true) ?? [],
            'urlCount' => (int) $row->url_count,
            'successful' => (bool) $row->successful,
            'statusCode' => $row->status_code !== null ? (int) $row->status_code : null,
            'error' => $row->error,
            'createdAt' => $row->created_at,
        ])->all();

        return $this->json(['data' => $data]);
    }
}
