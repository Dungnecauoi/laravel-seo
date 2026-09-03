<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Seo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists records of one exposed model type, each resolved through the same
 * pipeline a real page render would use — the JSON twin of
 * {@see \Duxbo\Seo\Http\Controllers\Panel\ContentController}.
 */
final class ContentController extends ApiController
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $exposed = $this->exposedModelTypes();

        $type = $request->query('type');
        $type = is_string($type) && in_array($type, $exposed, true) ? $type : ($exposed[0] ?? null);

        if ($type === null) {
            return $this->json([
                'exposedTypes' => $exposed,
                'type' => null,
                'data' => [],
                'meta' => null,
            ]);
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw new NotFoundHttpException("Unknown type [{$type}].");
        }

        /** @var Model $probe */
        $probe = new $class();

        $paginator = $class::query()->latest($probe->getKeyName())->paginate(20)->withQueryString();

        $rows = [];

        foreach ($paginator->items() as $record) {
            if (! $record instanceof Seoable) {
                continue;
            }

            $data = $this->seo->for($record);

            $rows[] = [
                'id' => $record->seoKey(),
                'title' => $data->title,
                'description' => $data->description,
                'robots' => $data->robotsLine(),
                'url' => $record->seoUrl(),
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
