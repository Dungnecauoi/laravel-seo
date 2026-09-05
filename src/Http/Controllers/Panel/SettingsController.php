<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;

/**
 * The top half is read-only status — the effective config a deploy set,
 * including the demo-domain switch, whether editable here or not. The
 * bottom half, when `seo.settings.enabled` is true, is the same
 * allowlisted keys {@see \Duxbo\Seo\Http\Api\V1\DynamicSettingsController}
 * exposes over the REST API, edited here instead through the session the
 * rest of this panel already uses.
 */
final class SettingsController
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

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

            'dynamicSettingsEnabled' => $this->settings->enabled(),
            'dynamicSettings' => $this->dynamicSettingsForView(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function dynamicSettingsForView(): array
    {
        if (! $this->settings->enabled()) {
            return [];
        }

        $data = [];

        foreach ($this->settings->allowedKeys() as $key) {
            $overridden = $this->settings->has($key);

            if ($this->settings->isSecret($key)) {
                $raw = $overridden ? $this->settings->get($key) : config("seo.{$key}");

                $data[$key] = [
                    'is_set' => is_string($raw) && $raw !== '',
                    'overridden' => $overridden,
                    'secret' => true,
                ];

                continue;
            }

            $data[$key] = [
                'value' => $overridden ? $this->settings->get($key) : config("seo.{$key}"),
                'overridden' => $overridden,
                'secret' => false,
            ];
        }

        return $data;
    }
}
