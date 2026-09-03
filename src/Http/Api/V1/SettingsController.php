<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Illuminate\Http\JsonResponse;

/**
 * Read-only. The JSON twin of
 * {@see \Duxbo\Seo\Http\Controllers\Panel\SettingsController} — what is
 * actually in effect, including the demo-domain master switch, without
 * exposing a way to write config back to disk over HTTP.
 */
final class SettingsController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'seoEnabled' => config('seo.enabled', true) === true,
            'indexableEnvironments' => config('seo.indexable_environments', []),
            'currentEnvironment' => app()->environment(),
            'apiEnabled' => config('seo.api.enabled', false) === true,
            'panelEnabled' => config('seo.panel.enabled', false) === true,
            'exposedModels' => config('seo.api.models', []),
            'allowedHosts' => config('seo.redirects.allowed_hosts', []),
            'sitemapSourceCount' => count(config('seo.sitemap.sources', [])),
            'aiDriver' => config('seo.ai.default', 'null'),
            'aiBudget' => config('seo.ai.daily_token_budget', 0),
            'analysisRateLimit' => config('seo.analysis.rate_limit', '30,1'),
            'supportedLocales' => config('seo.locales.supported', []),
        ]);
    }
}
