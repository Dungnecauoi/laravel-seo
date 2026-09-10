<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read side of `php artisan seo:internal-links` — one row per record of the
 * requested type, with how many internal links point at it, so a UI can
 * flag `incomingLinks === 0` as an orphan without re-running the crawl.
 */
final class InternalLinksController extends ApiController
{
    public function index(Request $request, UrlGenerator $urls): JsonResponse
    {
        $exposed = $this->exposedModelTypes();

        $type = $request->query('type');
        $type = is_string($type) && in_array($type, $exposed, true) ? $type : ($exposed[0] ?? null);

        if ($type === null) {
            return $this->json(['exposedTypes' => $exposed, 'type' => null, 'data' => [], 'meta' => null]);
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw new NotFoundHttpException("Unknown type [{$type}].");
        }

        /** @var Model $probe */
        $probe = new $class();

        $paginator = $class::query()->latest($probe->getKeyName())->paginate(20)->withQueryString();

        $table = (string) config('seo.internal_links.table', 'seo_internal_links');

        // Opt-in: a single-language site never needs to pass this, and
        // omitting it counts links across every locale in the table, the
        // same behaviour this endpoint always had. A multi-language site
        // must pass it — otherwise incoming/outgoing counts mix rows a
        // per-locale crawl (see seo:internal-links --locale) kept separate
        // on purpose.
        $locale = $request->query('locale');
        $locale = is_string($locale) && $locale !== '' ? $locale : null;

        $rows = [];

        foreach ($paginator->items() as $record) {
            if (! $record instanceof Seoable) {
                continue;
            }

            $path = parse_url($urls->absolute($record->seoUrl()), PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? rtrim($path, '/') : '/';
            $path = $path === '' ? '/' : $path;

            $incoming = DB::table($table)
                ->where('target_hash', md5($path))
                ->when($locale !== null, static fn ($q) => $q->where('locale', $locale))
                ->count();
            $outgoing = DB::table($table)
                ->where('source_type', $record->seoType())
                ->where('source_id', (string) $record->seoKey())
                ->when($locale !== null, static fn ($q) => $q->where('locale', $locale))
                ->count();

            $rows[] = [
                'id' => $record->seoKey(),
                'url' => $record->seoUrl(),
                'incomingLinks' => $incoming,
                'outgoingLinks' => $outgoing,
                'isOrphan' => $incoming === 0,
            ];
        }

        return $this->json([
            'exposedTypes' => $exposed,
            'type' => $type,
            'data' => $rows,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Read side of the actual source→target rows `seo:internal-links`
     * wrote — {@see index()} above only ever aggregated them into counts.
     * Paginates the raw table directly, across every source_type present
     * rather than one exposed model type at a time the way index() does.
     */
    public function detail(Request $request): JsonResponse
    {
        $table = (string) config('seo.internal_links.table', 'seo_internal_links');

        $type = $request->query('type');
        $locale = $request->query('locale');
        $query = DB::table($table)->orderByDesc('id');

        if (is_string($type) && $type !== '') {
            $query->where('source_type', $type);
        }

        if (is_string($locale) && $locale !== '') {
            $query->where('locale', $locale);
        }

        $paginator = $query->paginate(20)->withQueryString();
        $sourceUrls = $this->resolveSourceUrls($paginator->items());

        $data = array_map(static function (object $row) use ($sourceUrls): array {
            $key = $row->source_type.':'.$row->source_id;

            return [
                'sourceType' => (string) $row->source_type,
                'sourceId' => (string) $row->source_id,
                'sourceUrl' => $sourceUrls[$key] ?? null,
                'targetUrl' => (string) $row->target_url,
                'anchorText' => $row->anchor_text !== null ? (string) $row->anchor_text : null,
                'locale' => $row->locale,
                'createdAt' => $row->created_at,
            ];
        }, $paginator->items());

        return $this->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * One query per distinct source_type on the current page, not one per
     * row — a crawl typically covers one model at a time anyway, so this
     * is usually a single extra query. Deliberately does not gate on
     * seo.api.models: that allowlist exists to stop a caller guessing
     * class names via a request parameter, and source_type here only ever
     * comes from rows the crawl command itself wrote, never from a caller.
     *
     * @param  list<object>  $rows
     * @return array<string, string> "{type}:{id}" => seoUrl()
     */
    private function resolveSourceUrls(array $rows): array
    {
        $byType = [];

        foreach ($rows as $row) {
            $byType[(string) $row->source_type][] = (string) $row->source_id;
        }

        $urls = [];

        foreach ($byType as $type => $ids) {
            $class = Relation::getMorphedModel($type) ?? $type;

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            /** @var Model $probe */
            $probe = new $class();

            foreach ($class::query()->whereIn($probe->getKeyName(), $ids)->get() as $record) {
                if ($record instanceof Seoable) {
                    $urls[$type.':'.(string) $record->seoKey()] = $record->seoUrl();
                }
            }
        }

        return $urls;
    }
}
