<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;

/**
 * Read-only. Editing config/seo.php through a web form would mean either
 * writing PHP back to disk (fragile, and a deploy can overwrite it anyway)
 * or a second, database-backed settings store shadowing the config file —
 * both a bigger commitment than this panel makes. Showing what is actually
 * in effect, including the demo-domain switch, is the useful 80% without it.
 */
final class SettingsController
{
    public function __invoke(): View
    {
        return view('seo::panel.settings', [
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
