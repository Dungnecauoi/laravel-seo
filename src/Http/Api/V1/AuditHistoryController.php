<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Audit\AuditBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read side of `php artisan seo:audit` — the batches it wrote, so a UI can
 * chart average score over time instead of reading console output.
 */
final class AuditHistoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $model = $request->query('model');

        $query = AuditBatch::query()->latest('id');

        if (is_string($model) && $model !== '') {
            $query->where('model', $model);
        }

        $paginator = $query->paginate(20);

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
