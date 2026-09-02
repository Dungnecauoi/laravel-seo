<?php

declare(strict_types=1);

namespace Duxbo\Seo\Formatters;

use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\OutputFormatter;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;

/**
 * The exact object Next.js App Router expects from `generateMetadata()`.
 *
 * Returning our own shape and asking the front end to translate it would put
 * the mapping in the one place nobody maintains. This way:
 *
 *     export async function generateMetadata({ params }) {
 *       const r = await fetch(`${API}/api/seo/v1/resolve?url=${params.slug}&format=next`)
 *       return r.json()
 *     }
 */
final class NextMetadataFormatter implements OutputFormatter
{
    public function __construct(
        private readonly LocaleResolver $locales,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function name(): string
    {
        return 'next';
    }

    /**
     * @return array<string, mixed>
     */
    public function format(SeoContext $context): array
    {
        $data = $context->data;
        $og = $data->openGraph;
        $twitter = $data->twitter;

        $metadata = array_filter([
            'title' => $data->title,
            'description' => $data->description,
            'keywords' => $data->secondaryKeywords !== [] ? $data->secondaryKeywords : null,
        ], static fn (mixed $v): bool => $v !== null);

        $canonical = $data->canonical ?? $context->url;
        $languages = $this->languages($context);

        $metadata['alternates'] = array_filter([
            'canonical' => $canonical,
            'languages' => $languages !== [] ? $languages : null,
        ], static fn (mixed $v): bool => $v !== null);

        $metadata['openGraph'] = array_filter([
            'title' => $og?->title ?? $data->title,
            'description' => $og?->description ?? $data->description,
            'url' => $og?->url ?? $canonical,
            'siteName' => $og?->siteName,
            'type' => $og?->type ?? 'website',
            'locale' => $og?->locale ?? $context->locale,
            'images' => $og?->image !== null ? [array_filter([
                'url' => $this->urls->absolute($og->image),
                'alt' => $og->imageAlt,
                'width' => $og->imageWidth,
                'height' => $og->imageHeight,
            ], static fn (mixed $v): bool => $v !== null)] : null,
        ], static fn (mixed $v): bool => $v !== null);

        if ($twitter !== null && ! $twitter->isEmpty()) {
            $metadata['twitter'] = array_filter([
                'card' => $twitter->card?->value,
                'site' => $twitter->site,
                'creator' => $twitter->creator,
                'title' => $twitter->title ?? $data->title,
                'description' => $twitter->description ?? $data->description,
                'images' => $twitter->image !== null ? [$this->urls->absolute($twitter->image)] : null,
            ], static fn (mixed $v): bool => $v !== null);
        }

        // Next expects robots as booleans, not a directive string.
        if ($data->robots !== []) {
            $metadata['robots'] = array_filter([
                'index' => $data->hasRobotsDirective('noindex') ? false : null,
                'follow' => $data->hasRobotsDirective('nofollow') ? false : null,
            ], static fn (mixed $v): bool => $v !== null);
        }

        return $metadata;
    }

    /**
     * @return array<string, string>
     */
    private function languages(SeoContext $context): array
    {
        $supported = $this->locales->supported();

        if (count($supported) < 2) {
            return [];
        }

        $languages = [];

        foreach ($supported as $locale) {
            $languages[$locale] = $this->urls->alternate($context->url, $locale);
        }

        return $languages;
    }
}
