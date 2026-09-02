<?php

declare(strict_types=1);

namespace Duxbo\Seo\Formatters;

use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\OutputFormatter;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Support\HtmlString;

/**
 * Renders meta tags for a Blade layout.
 *
 * Every value is escaped here regardless of what earlier stages did: a title
 * mapped straight from user-submitted content reaches this point as ordinary
 * text, and an unescaped quote in an attribute is an injection point.
 */
final class HtmlFormatter implements OutputFormatter
{
    public function __construct(
        private readonly LocaleResolver $locales,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function name(): string
    {
        return 'html';
    }

    public function format(SeoContext $context): HtmlString
    {
        $data = $context->data;
        $lines = [];

        if ($data->title !== null) {
            $lines[] = '<title>'.self::e($data->title).'</title>';
        }

        if ($data->description !== null) {
            $lines[] = self::meta('name', 'description', $data->description);
        }

        if ($data->canonical !== null) {
            $lines[] = '<link rel="canonical" href="'.self::e($data->canonical).'">';
        }

        $robots = $data->robotsLine();

        if ($robots !== null) {
            $lines[] = self::meta('name', 'robots', $robots);
        }

        foreach ($this->hreflang($context) as $line) {
            $lines[] = $line;
        }

        foreach ($this->openGraph($context) as $line) {
            $lines[] = $line;
        }

        foreach ($this->twitter($context) as $line) {
            $lines[] = $line;
        }

        return new HtmlString(implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    private function hreflang(SeoContext $context): array
    {
        $supported = $this->locales->supported();

        // One language needs no alternates, and emitting a single self-
        // referential hreflang is a common way to get the whole cluster ignored.
        if (count($supported) < 2) {
            return [];
        }

        $lines = [];

        foreach ($supported as $locale) {
            $url = $this->urls->alternate($context->url, $locale);
            $lines[] = '<link rel="alternate" hreflang="'.self::e($locale).'" href="'.self::e($url).'">';
        }

        $lines[] = '<link rel="alternate" hreflang="x-default" href="'.self::e($context->url).'">';

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function openGraph(SeoContext $context): array
    {
        $data = $context->data;
        $og = $data->openGraph;

        // Open Graph falls back to the page's own title and description rather
        // than being omitted: a link with no preview is worse than a plain one.
        $title = $og?->title ?? $data->title;
        $description = $og?->description ?? $data->description;
        $url = $og?->url ?? $data->canonical ?? $context->url;

        $lines = [];

        $lines[] = self::meta('property', 'og:type', $og?->type ?? 'website');
        $lines[] = self::meta('property', 'og:url', $url);

        if ($title !== null) {
            $lines[] = self::meta('property', 'og:title', $title);
        }

        if ($description !== null) {
            $lines[] = self::meta('property', 'og:description', $description);
        }

        if ($og?->siteName !== null) {
            $lines[] = self::meta('property', 'og:site_name', $og->siteName);
        }

        if ($og?->image !== null) {
            $lines[] = self::meta('property', 'og:image', $this->urls->absolute($og->image));

            if ($og->imageAlt !== null) {
                $lines[] = self::meta('property', 'og:image:alt', $og->imageAlt);
            }

            if ($og->imageWidth !== null) {
                $lines[] = self::meta('property', 'og:image:width', (string) $og->imageWidth);
            }

            if ($og->imageHeight !== null) {
                $lines[] = self::meta('property', 'og:image:height', (string) $og->imageHeight);
            }
        }

        $locale = $og?->locale ?? $context->locale;

        if ($locale !== null) {
            $lines[] = self::meta('property', 'og:locale', $locale);
        }

        foreach ($og?->alternateLocales ?? [] as $alternate) {
            $lines[] = self::meta('property', 'og:locale:alternate', $alternate);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function twitter(SeoContext $context): array
    {
        $data = $context->data;
        $twitter = $data->twitter;

        if ($twitter === null || $twitter->isEmpty()) {
            return [];
        }

        $lines = [];

        if ($twitter->card !== null) {
            $lines[] = self::meta('name', 'twitter:card', $twitter->card->value);
        }

        foreach ([
            'twitter:site' => $twitter->site,
            'twitter:creator' => $twitter->creator,
            'twitter:title' => $twitter->title ?? $data->title,
            'twitter:description' => $twitter->description ?? $data->description,
        ] as $property => $value) {
            if ($value !== null) {
                $lines[] = self::meta('name', $property, $value);
            }
        }

        if ($twitter->image !== null) {
            $lines[] = self::meta('name', 'twitter:image', $this->urls->absolute($twitter->image));

            if ($twitter->imageAlt !== null) {
                $lines[] = self::meta('name', 'twitter:image:alt', $twitter->imageAlt);
            }
        }

        return $lines;
    }

    private static function meta(string $attribute, string $name, string $content): string
    {
        return sprintf(
            '<meta %s="%s" content="%s">',
            $attribute,
            self::e($name),
            self::e($content),
        );
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
