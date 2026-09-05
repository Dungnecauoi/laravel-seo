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

        $rows = [];

        foreach ($paginator->items() as $record) {
            if (! $record instanceof Seoable) {
                continue;
            }

            $path = parse_url($urls->absolute($record->seoUrl()), PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? rtrim($path, '/') : '/';
            $path = $path === '' ? '/' : $path;

            $incoming = DB::table($table)->where('target_hash', md5($path))->count();
            $outgoing = DB::table($table)
                ->where('source_type', $record->seoType())
                ->where('source_id', (string) $record->seoKey())
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
}
