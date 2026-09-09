<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\BrokenLinks;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Illuminate\Support\Facades\DB;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\BrokenLinksController}
 * — currently-known-broken URLs with which source records cite them, from
 * whatever `php artisan seo:broken-links` last found. Read-only: this never
 * re-runs the crawl or the network checks itself, {@see
 * \Duxbo\Seo\Ai\Tools\Console\RunBrokenLinksCommandTool} does that.
 */
final class ListBrokenLinksTool implements AiTool
{
    public function name(): string
    {
        return 'seo.broken_links.list';
    }

    public function description(): string
    {
        return 'List currently-known-broken external URLs and which records link to them, from the last '
            .'seo:broken-links run.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $checksTable = (string) config('seo.broken_links.checks_table', 'seo_link_checks');
        $externalTable = (string) config('seo.broken_links.external_table', 'seo_external_links');
        $page = max(1, (int) ($input['page'] ?? 1));

        $paginator = DB::table($checksTable)
            ->where('successful', false)
            ->orderByDesc('updated_at')
            ->paginate(20, page: $page);

        $data = collect($paginator->items())->map(function (object $row) use ($externalTable): array {
            $sources = DB::table($externalTable)
                ->where('target_hash', md5((string) $row->url))
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

        return [
            'data' => $data,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
