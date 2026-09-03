<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists records of one exposed model type, each resolved through the same
 * pipeline a real page render would use — cheap here because a paginated
 * page is at most a few dozen records, unlike the sitemap's entire table.
 */
final class ContentController
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function __invoke(Request $request): View
    {
        /** @var list<string> $exposed */
        $exposed = config('seo.api.models', []);

        $type = $request->query('type');
        $type = is_string($type) && in_array($type, $exposed, true) ? $type : ($exposed[0] ?? null);

        if ($type === null) {
            return view('seo::panel.content', [
                'exposedTypes' => $exposed,
                'type' => null,
                'rows' => [],
                'paginator' => null,
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

        return view('seo::panel.content', [
            'exposedTypes' => $exposed,
            'type' => $type,
            'rows' => $rows,
            'paginator' => $paginator,
        ]);
    }
}
