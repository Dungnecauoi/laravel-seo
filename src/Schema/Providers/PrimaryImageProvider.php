<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema\Providers;

use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Data\SeoContext;

/**
 * The page's main image, as its own node so several nodes can reference it
 * without repeating the URL and dimensions.
 */
final class PrimaryImageProvider implements SchemaProvider
{
    public static function idFor(SeoContext $context): string
    {
        return $context->url.'#primaryimage';
    }

    public function supports(SeoContext $context): bool
    {
        return $context->data->openGraph?->image !== null;
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
        $og = $context->data->openGraph;

        return [
            '@type' => 'ImageObject',
            'url' => $og?->image,
            'contentUrl' => $og?->image,
            'caption' => $og?->imageAlt,
            'width' => $og?->imageWidth,
            'height' => $og?->imageHeight,
        ];
    }

    public function priority(): int
    {
        return 30;
    }
}
