<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema\Providers;

use Duxbo\Seo\Contracts\HasSchema;
use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;

/**
 * Emits whatever a model declares through {@see HasSchema}.
 *
 * This is why there is no class per schema.org type here. Article, Product,
 * Recipe, JobPosting and the several hundred types nobody anticipated differ
 * only in which keys they carry, and a model already knows its own fields. The
 * work worth centralising is the wiring — `@id`, `mainEntityOfPage`, the
 * publisher link, ISO dates, absolute URLs — and that is what this does.
 */
final class ModelSchemaProvider implements SchemaProvider
{
    public function __construct(private readonly UrlGenerator $urls)
    {
    }

    public function supports(SeoContext $context): bool
    {
        return $context->model instanceof HasSchema;
    }

    public function id(SeoContext $context): string
    {
        return $context->url.'#entity';
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function build(SeoContext $context): array
    {
        /** @var HasSchema $model */
        $model = $context->model;

        $declared = $model->seoSchema($context);

        if ($declared === []) {
            return [];
        }

        $isList = isset($declared[0]) && is_array($declared[0]);

        if ($isList) {
            /** @var list<array<string, mixed>> $declared */
            return array_map(fn (array $node): array => $this->wire($node, $context), $declared);
        }

        /** @var array<string, mixed> $declared */
        return $this->wire($declared, $context);
    }

    public function priority(): int
    {
        return 60;
    }

    /**
     * Attach the node to the page and the publisher, unless it said otherwise.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function wire(array $node, SeoContext $context): array
    {
        $node['mainEntityOfPage'] ??= ['@id' => WebPageProvider::idFor($context)];
        $node['url'] ??= $context->data->canonical ?? $context->url;

        // Content types are the ones Google expects a publisher on. Applying it
        // to every type would put a publisher on a Person or a Place.
        $type = $node['@type'] ?? null;

        if (is_string($type) && $this->takesPublisher($type)) {
            $node['publisher'] ??= ['@id' => OrganizationProvider::idFor($this->urls)];
        }

        if ($context->locale !== null) {
            $node['inLanguage'] ??= $context->locale;
        }

        return $node;
    }

    private function takesPublisher(string $type): bool
    {
        return in_array($type, [
            'Article', 'BlogPosting', 'NewsArticle', 'TechArticle', 'Report',
            'Recipe', 'HowTo', 'Course', 'VideoObject', 'JobPosting', 'Dataset',
        ], true);
    }
}
