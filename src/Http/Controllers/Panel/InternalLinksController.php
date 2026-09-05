<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Contracts\UrlGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class InternalLinksController
{
    public function __invoke(Request $request, UrlGenerator $urls): View
    {
        /** @var list<string> $exposed */
        $exposed = config('seo.api.models', []);

        $type = $request->query('type');
        $type = is_string($type) && in_array($type, $exposed, true) ? $type : ($exposed[0] ?? null);

        if ($type === null) {
            return view('seo::panel.internal-links', [
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
                'url' => $record->seoUrl(),
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'isOrphan' => $incoming === 0,
            ];
        }

        return view('seo::panel.internal-links', [
            'exposedTypes' => $exposed,
            'type' => $type,
            'rows' => $rows,
            'paginator' => $paginator,
        ]);
    }
}
