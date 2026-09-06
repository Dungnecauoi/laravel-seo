<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read side of {@see \Duxbo\Seo\Ai\Tools\AiToolDispatcher}'s own logging —
 * every propose and apply, so a human can see what an AI agent has been
 * doing without reading the table directly.
 */
final class AiToolCallsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $paginator = DB::table($this->table())->latest('id')->paginate(20);

        $data = array_map(static fn (object $row): array => [
            'id' => $row->id,
            'tool' => $row->tool,
            'riskTier' => $row->risk_tier,
            'status' => $row->status,
            'proposalId' => $row->proposal_id,
            'input' => json_decode((string) $row->input, true) ?? [],
            'output' => $row->output !== null ? json_decode((string) $row->output, true) : null,
            'preview' => $row->preview,
            'scope' => $row->scope,
            'createdAt' => $row->created_at,
            'appliedAt' => $row->applied_at,
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

    private function table(): string
    {
        return (string) config('seo.ai.tools.table', 'seo_ai_tool_calls');
    }
}
