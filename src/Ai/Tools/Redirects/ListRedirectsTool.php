<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Redirects;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Redirects\Redirect;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\RedirectsController::index()}.
 */
final class ListRedirectsTool implements AiTool
{
    public function name(): string
    {
        return 'seo.redirects.list';
    }

    public function description(): string
    {
        return 'List redirect rules, newest first, with their source, target, type, status and hit count.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Defaults to 1.', 'minimum' => 1],
            ],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $paginator = Redirect::query()->latest('id')->paginate(20, page: $page);

        $data = array_map(static fn (Redirect $r): array => [
            'id' => $r->getKey(),
            'source' => $r->source_path,
            'target' => $r->target,
            'type' => $r->source_type->value,
            'status' => $r->status_code->value,
            'isActive' => $r->is_active,
            'locale' => $r->locale,
            'notes' => $r->notes,
            'hits' => $r->hits,
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
