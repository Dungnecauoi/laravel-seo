<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Stages;

use Closure;
use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\RobotsRule;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Resolution\SeoDataBuilder;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * Site-wide fallbacks, and the last stage that can supply a value.
 *
 * Also where staging is kept out of the index: outside production the default
 * robots rule is noindex. Forgetting to switch it on costs a day of traffic;
 * forgetting to switch it off lets staging compete with production for months.
 *
 * A stored per-page value beats that default deliberately — someone testing
 * SEO behaviour on staging may want to force one page to index. `seo.enabled
 * = false` is a different, stronger promise ("not this domain, full stop",
 * the safety net for a demo shown to a client before launch) and is applied
 * as an unconditional override after everything else, so no stored value or
 * template can quietly defeat it.
 */
final class GlobalDefaultStage implements ResolverStage
{
    public function __construct(
        private readonly Config $config,
        private readonly Application $app,
    ) {
    }

    public function handle(SeoContext $context, Closure $next): SeoContext
    {
        /** @var array<string, mixed> $defaults */
        $defaults = $this->config->get('seo.defaults', []);

        $defaults['canonical'] ??= $context->url;

        if (! $this->isIndexable()) {
            $defaults['robots'] = ['noindex', 'nofollow'];
        }

        $context = $context->fillMissing(SeoDataBuilder::fromDotted($defaults));

        if (! $this->siteSeoEnabled()) {
            $context = $context->withData($context->data->with(
                robots: [RobotsRule::noIndex(), RobotsRule::noFollow()],
            ));
        }

        return $next($context);
    }

    private function siteSeoEnabled(): bool
    {
        return $this->config->get('seo.enabled', true) === true;
    }

    private function isIndexable(): bool
    {
        $environments = $this->config->get('seo.indexable_environments', ['production']);

        if (! is_array($environments)) {
            return true;
        }

        /** @var list<string> $environments */
        return $this->app->environment($environments);
    }
}
