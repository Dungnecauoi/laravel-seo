<?php

declare(strict_types=1);

namespace Duxbo\Seo\Formatters;

use Duxbo\Seo\Contracts\LocaleResolver;
use Duxbo\Seo\Contracts\OutputFormatter;
use Duxbo\Seo\Contracts\UrlGenerator;
use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Locale\AlternateLocaleResolver;
use Duxbo\Seo\Support\SiteVerification;

/**
 * The payload Unhead takes — Nuxt's `useHead()` and Vue's `@unhead/vue`.
 *
 * One formatter for both, because Nuxt 3 is built on Unhead and the shape is
 * identical. Registered under two names so the front end asks for whichever
 * word matches its own framework.
 */
final class HeadFormatter implements OutputFormatter
{
    public function __construct(
        private readonly LocaleResolver $locales,
        private readonly UrlGenerator $urls,
        private readonly AlternateLocaleResolver $alternateLocales,
        private readonly SiteVerification $verification,
        private readonly string $name = 'head',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function withName(string $name): self
    {
        return new self($this->locales, $this->urls, $this->alternateLocales, $this->verification, $name);
    }

    /**
     * @return array<string, mixed>
     */
    public function format(SeoContext $context): array
    {
        $data = $context->data;
        $og = $data->openGraph;
        $twitter = $data->twitter;
        $canonical = $data->canonical ?? $context->url;

        $meta = [];
        $link = [['rel' => 'canonical', 'href' => $canonical]];

        if ($data->description !== null) {
            $meta[] = ['name' => 'description', 'content' => $data->description];
        }

        $robots = $data->robotsLine();

        if ($robots !== null) {
            $meta[] = ['name' => 'robots', 'content' => $robots];
        }

        foreach ($this->verification->metaTags() as $name => $content) {
            $meta[] = ['name' => $name, 'content' => $content];
        }

        $meta[] = ['property' => 'og:type', 'content' => $og?->type ?? 'website'];
        $meta[] = ['property' => 'og:url', 'content' => $og?->url ?? $canonical];

        foreach ([
            'og:title' => $og?->title ?? $data->title,
            'og:description' => $og?->description ?? $data->description,
            'og:site_name' => $og?->siteName,
            'og:locale' => $og?->locale ?? $context->locale,
        ] as $property => $value) {
            if ($value !== null) {
                $meta[] = ['property' => $property, 'content' => $value];
            }
        }

        if ($og?->image !== null) {
            $meta[] = ['property' => 'og:image', 'content' => $this->urls->absolute($og->image)];

            if ($og->imageAlt !== null) {
                $meta[] = ['property' => 'og:image:alt', 'content' => $og->imageAlt];
            }
        }

        // article:* belongs only under og:type=article — emitting it on a
        // 'website' page is meaningless per the Open Graph spec, and
        // Facebook's own parser ignores it there regardless.
        if ($og !== null && $og->isArticle()) {
            foreach ([
                'article:published_time' => $og->publishedTime,
                'article:modified_time' => $og->modifiedTime,
                'article:author' => $og->author,
                'article:section' => $og->section,
            ] as $property => $value) {
                if ($value !== null) {
                    $meta[] = ['property' => $property, 'content' => $value];
                }
            }

            foreach ($og->tags as $tag) {
                $meta[] = ['property' => 'article:tag', 'content' => $tag];
            }
        }

        if ($twitter !== null && ! $twitter->isEmpty()) {
            foreach ([
                'twitter:card' => $twitter->card?->value,
                'twitter:site' => $twitter->site,
                'twitter:creator' => $twitter->creator,
                'twitter:title' => $twitter->title ?? $data->title,
                'twitter:description' => $twitter->description ?? $data->description,
            ] as $name => $value) {
                if ($value !== null) {
                    $meta[] = ['name' => $name, 'content' => $value];
                }
            }

            if ($twitter->image !== null) {
                $meta[] = ['name' => 'twitter:image', 'content' => $this->urls->absolute($twitter->image)];
            }
        }

        // See HtmlFormatter::hreflang() for why a model-backed page only
        // claims locales AlternateLocaleResolver can vouch for, while a
        // static route falls back to the site-wide list.
        $locales = $context->model !== null
            ? $this->alternateLocales->resolve($context->model, $context->locale)
            : $this->locales->supported();

        if (count($locales) >= 2) {
            foreach ($locales as $locale) {
                $link[] = [
                    'rel' => 'alternate',
                    'hreflang' => $locale,
                    'href' => $this->urls->alternate($context->url, $locale),
                ];
            }

            $link[] = ['rel' => 'alternate', 'hreflang' => 'x-default', 'href' => $context->url];
        }

        return array_filter([
            'title' => $data->title,
            'meta' => $meta,
            'link' => $link,
            'htmlAttrs' => $context->locale !== null ? ['lang' => $context->locale] : null,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
