<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution\Stages;

use Closure;
use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Resolution\SeoDataBuilder;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Values the model itself offers — `seoDefaults()`, `$seoMap`, or config.
 *
 * Three ways to declare the mapping, because a model you own takes a method, a
 * model from a package you cannot edit takes config, and a trivial case takes a
 * property. Precedence is method, then property, then config.
 */
final class ModelAttributeStage implements ResolverStage
{
    public function __construct(private readonly Config $config)
    {
    }

    public function handle(SeoContext $context, Closure $next): SeoContext
    {
        if (! $context->hasModel()) {
            return $next($context);
        }

        $model = $context->model;
        $declared = $model->seoDefaults();

        if ($declared === []) {
            /** @var array<string, string> $mapping */
            $mapping = $this->config->get('seo.models.'.$model::class.'.map', []);
            $declared = $this->applyMap($mapping, $context);
        }

        if ($declared !== []) {
            $context = $context->fillMissing(SeoDataBuilder::fromDotted($declared));
        }

        return $next($context);
    }

    /**
     * @param  array<string, string>  $mapping  SEO key => model attribute name.
     * @return array<string, mixed>
     */
    private function applyMap(array $mapping, SeoContext $context): array
    {
        $values = [];

        foreach ($mapping as $seoKey => $attribute) {
            $value = $context->attribute($attribute);

            if ($value !== null && $value !== '') {
                $values[$seoKey] = $value;
            }
        }

        return $values;
    }
}
