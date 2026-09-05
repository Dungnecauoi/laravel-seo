<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\InternalLinks;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Exceptions\AiToolNotFound;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\InternalLinksController}
 * — one row per record of the requested type, with how many internal links
 * point at it, so `incomingLinks === 0` flags an orphan without re-running
 * `seo:internal-links`.
 */
final class ListInternalLinksTool implements AiTool
{
    use ResolvesExposedModel;

    public function __construct(private readonly UrlGenerator $urls)
    {
    }

    public function name(): string
    {
        return 'seo.internal_links.list';
    }

    public function description(): string
    {
        return 'List records of one type with their incoming/outgoing internal link counts; incomingLinks 0 means orphaned.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'description' => 'Morph alias, one of seo.api.models. Defaults to the first exposed type.'],
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
        $exposed = $this->exposedModelTypes();
        $requested = isset($input['type']) ? (string) $input['type'] : null;
        $type = $requested !== null && in_array($requested, $exposed, true) ? $requested : ($exposed[0] ?? null);

        if ($type === null) {
            return ['exposedTypes' => $exposed, 'type' => null, 'data' => [], 'meta' => null];
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw AiToolNotFound::named("type:{$type}", $exposed);
        }

        /** @var Model $probe */
        $probe = new $class();
        $page = max(1, (int) ($input['page'] ?? 1));

        $paginator = $class::query()->latest($probe->getKeyName())->paginate(20, page: $page);
        $table = (string) config('seo.internal_links.table', 'seo_internal_links');

        $rows = [];

        foreach ($paginator->items() as $record) {
            if (! $record instanceof Seoable) {
                continue;
            }

            $path = parse_url($this->urls->absolute($record->seoUrl()), PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? rtrim($path, '/') : '/';
            $path = $path === '' ? '/' : $path;

            $incoming = DB::table($table)->where('target_hash', md5($path))->count();

            $rows[] = [
                'id' => $record->seoKey(),
                'url' => $record->seoUrl(),
                'incomingLinks' => $incoming,
                'outgoingLinks' => DB::table($table)
                    ->where('source_type', $record->seoType())
                    ->where('source_id', (string) $record->seoKey())
                    ->count(),
                'isOrphan' => $incoming === 0,
            ];
        }

        return [
            'exposedTypes' => $exposed,
            'type' => $type,
            'data' => $rows,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
