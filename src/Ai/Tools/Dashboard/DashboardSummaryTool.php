<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Dashboard;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Sitemap\SitemapGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\DashboardController} —
 * one call for "how is SEO doing on this site right now," the natural first
 * thing an AI agent would want before deciding what else to look at.
 */
final class DashboardSummaryTool implements AiTool
{
    use ResolvesExposedModel;

    public function __construct(
        private readonly MetadataRepository $repository,
        private readonly SitemapGenerator $sitemap,
    ) {
    }

    public function name(): string
    {
        return 'seo.dashboard.summary';
    }

    public function description(): string
    {
        return 'Site-wide SEO snapshot: records with/without metadata by type, active redirects, 404 count, sitemap sources.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $exposed = $this->exposedModelTypes();

        $missing = [];
        $total = 0;

        foreach ($exposed as $alias) {
            $class = Relation::getMorphedModel($alias) ?? $alias;

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $total += $class::query()->count();
            $missing[$alias] = $this->repository->missing($class)->count();
        }

        return [
            'seoEnabled' => config('seo.enabled', true) === true,
            'totalRecords' => $total,
            'missingByType' => $missing,
            'totalMissing' => array_sum($missing),
            'activeRedirects' => Redirect::query()->where('is_active', true)->count(),
            'notFoundCount' => DB::table((string) config('seo.not_found.table', 'seo_not_found'))->count(),
            'sitemapSources' => count($this->sitemap->sources()),
            'exposedTypes' => $exposed,
        ];
    }
}
