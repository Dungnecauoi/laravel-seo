<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Stages;

use Closure;
use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Resolution\SeoDataBuilder;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Per-model templates, still containing tokens.
 *
 * Templates are what make the package usable without anyone filling in a form:
 * `%title% %sep% %sitename%` covers most pages on most sites forever.
 */
final class TemplateStage implements ResolverStage
{
    public function __construct(private readonly Config $config)
    {
    }

    public function handle(SeoContext $context, Closure $next): SeoContext
    {
        if (! $context->hasModel()) {
            return $next($context);
        }

        /** @var array<string, mixed> $template */
        $template = $this->config->get('seo.models.'.$context->model::class.'.template', []);

        if ($template !== []) {
            $context = $context->fillMissing(SeoDataBuilder::fromDotted($template));
        }

        return $next($context);
    }
}
