<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\NotFound;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\DB;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\NotFoundController::index()}.
 *
 * Every field here was supplied by whoever made the original 404 request —
 * path, referrer and user agent alike — so it is escaped the same way the
 * HTTP endpoint escapes it, on the assumption a caller may render this
 * somewhere.
 */
final class ListNotFoundTool implements AiTool
{
    public function __construct(private readonly Config $config)
    {
    }

    public function name(): string
    {
        return 'seo.not_found.list';
    }

    public function description(): string
    {
        return 'List the most-hit 404 paths logged by this site, for spotting broken links worth redirecting.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'Defaults to 50, capped at 200.', 'minimum' => 1, 'maximum' => 200],
            ],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $limit = min(200, max(1, (int) ($input['limit'] ?? 50)));

        $rows = DB::table((string) $this->config->get('seo.not_found.table', 'seo_not_found'))
            ->orderByDesc('hits')
            ->limit($limit)
            ->get();

        return [
            'data' => $rows->map(static fn (object $row): array => [
                'id' => $row->id,
                'path' => e((string) $row->path),
                'hits' => (int) $row->hits,
                'referrer' => $row->referrer !== null ? e((string) $row->referrer) : null,
                'user_agent' => $row->user_agent !== null ? e((string) $row->user_agent) : null,
                'first_seen_at' => $row->first_seen_at,
                'last_seen_at' => $row->last_seen_at,
            ])->all(),
        ];
    }
}
