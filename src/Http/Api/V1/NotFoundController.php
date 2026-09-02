<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reads the 404 log.
 *
 * Every field here was supplied by whoever made the request — path, referrer
 * and user agent alike. Rendering any of it raw in a panel is stored XSS, so it
 * is escaped on the way out and the docs say so for anyone building their own UI.
 */
final class NotFoundController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $rows = DB::table((string) config('seo.not_found.table', 'seo_not_found'))
            ->orderByDesc('hits')
            ->limit(min(200, max(1, (int) $request->query('limit', '50'))))
            ->get();

        $entries = $rows->map(static fn (object $row): array => [
            'id' => $row->id,
            'path' => e((string) $row->path),
            'hits' => (int) $row->hits,
            'referrer' => $row->referrer !== null ? e((string) $row->referrer) : null,
            'user_agent' => $row->user_agent !== null ? e((string) $row->user_agent) : null,
            'first_seen_at' => $row->first_seen_at,
            'last_seen_at' => $row->last_seen_at,
        ])->all();

        return $this->json(['data' => $entries]);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table((string) config('seo.not_found.table', 'seo_not_found'))->where('id', $id)->delete();

        return $this->json(['deleted' => true]);
    }
}
