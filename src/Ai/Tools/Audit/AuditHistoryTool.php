<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Audit;

use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\AuditHistoryController}
 * — the read side of `php artisan seo:audit`, so an agent can see score
 * trends without re-running the crawl itself.
 */
final class AuditHistoryTool implements AiTool
{
    public function name(): string
    {
        return 'seo.audit.history';
    }

    public function description(): string
    {
        return 'List past seo:audit batches with their average/min/max score, optionally filtered by model.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'model' => ['type' => 'string', 'description' => 'Fully-qualified model class to filter by.'],
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
        $query = AuditBatch::query()->latest('id');

        if (isset($input['model']) && $input['model'] !== '') {
            $query->where('model', (string) $input['model']);
        }

        $page = max(1, (int) ($input['page'] ?? 1));
        $paginator = $query->paginate(20, page: $page);

        $data = array_map(static fn (AuditBatch $batch): array => [
            'id' => $batch->id,
            'model' => $batch->model,
            'locale' => $batch->locale,
            'totalRecords' => $batch->total_records,
            'averageScore' => $batch->average_score,
            'minScore' => $batch->min_score,
            'maxScore' => $batch->max_score,
            'startedAt' => optional($batch->started_at)->toIso8601String(),
            'finishedAt' => optional($batch->finished_at)->toIso8601String(),
        ], $paginator->items());

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
