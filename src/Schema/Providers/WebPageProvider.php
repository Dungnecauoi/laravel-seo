<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema\Providers;

use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;

/**
 * The page being viewed — the hub the other nodes hang off.
 */
final class WebPageProvider implements SchemaProvider
{
    public function __construct(private readonly UrlGenerator $urls)
    {
    }

    public static function idFor(SeoContext $context): string
    {
        return $context->url.'#webpage';
    }

    public function supports(SeoContext $context): bool
    {
        return true;
    }

    public function id(SeoContext $context): string
    {
        return self::idFor($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(SeoContext $context): array
    {
        $data = $context->data;

        // References are emitted unconditionally; the assembler prunes any
        // whose target was not registered, so an optional provider sitting out
        // never leaves a dangling @id behind.
        $node = [
            '@type' => 'WebPage',
            'url' => $data->canonical ?? $context->url,
            'name' => $data->title,
            'description' => $data->description,
            'isPartOf' => ['@id' => WebSiteProvider::idFor($this->urls)],
            'breadcrumb' => ['@id' => BreadcrumbProvider::idFor($context)],
            'primaryImageOfPage' => ['@id' => PrimaryImageProvider::idFor($context)],
        ];

        if ($context->locale !== null) {
            $node['inLanguage'] = $context->locale;
        }

        foreach (['datePublished' => 'published_at', 'dateModified' => 'updated_at'] as $key => $attribute) {
            $value = $context->attribute($attribute);

            if ($value !== null) {
                $node[$key] = $value;
            }
        }

        return $node;
    }

    public function priority(): int
    {
        return 50;
    }
}
