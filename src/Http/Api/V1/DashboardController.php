<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Sitemap\SitemapGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * The JSON twin of {@see \Duxbo\Seo\Http\Controllers\Panel\DashboardController}
 * — same stats, for a React/Vue admin surface instead of the Blade one.
 */
final class DashboardController extends ApiController
{
    public function __construct(
        private readonly MetadataRepository $repository,
        private readonly SitemapGenerator $sitemap,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $exposed = $this->exposedModelTypes();

        $missing = [];
        $total = 0;

        foreach ($exposed as $alias) {
            $class = Relation::getMorphedModel($alias) ?? $alias;

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $count = $class::query()->count();
            $missingCount = $this->repository->missing($class)->count();

            $total += $count;
            $missing[$alias] = $missingCount;
        }

        return $this->json([
            'seoEnabled' => config('seo.enabled', true) === true,
            'totalRecords' => $total,
            'missingByType' => $missing,
            'totalMissing' => array_sum($missing),
            'activeRedirects' => Redirect::query()->where('is_active', true)->count(),
            'notFoundCount' => DB::table((string) config('seo.not_found.table', 'seo_not_found'))->count(),
            'sitemapSources' => count($this->sitemap->sources()),
            'exposedTypes' => $exposed,
        ]);
    }
}
