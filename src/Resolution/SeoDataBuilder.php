<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution;

use Duxbo\Seo\Data\OpenGraphData;
use Duxbo\Seo\Data\RobotsRule;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Data\TwitterData;
use Duxbo\Seo\Enums\RobotsDirective;
use Duxbo\Seo\Enums\TwitterCard;

/**
 * Builds a SeoData object from a flat, dot-notated array.
 *
 * This is the shape a model's `seoDefaults()` returns and the shape config
 * templates use, so a project writes `'og.image' => $this->cover_url` rather
 * than constructing nested value objects by hand.
 */
final class SeoDataBuilder
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromDotted(array $values): SeoData
    {
        $og = [];
        $twitter = [];
        $flat = [];

        foreach ($values as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (str_starts_with($key, 'og.')) {
                $og[substr($key, 3)] = $value;
            } elseif (str_starts_with($key, 'twitter.')) {
                $twitter[substr($key, 8)] = $value;
            } else {
                $flat[$key] = $value;
            }
        }

        return new SeoData(
            title: self::str($flat['title'] ?? null),
            description: self::str($flat['description'] ?? null),
            canonical: self::str($flat['canonical'] ?? null),
            robots: self::robots($flat['robots'] ?? null),
            openGraph: $og === [] ? null : self::openGraph($og),
            twitter: $twitter === [] ? null : self::twitter($twitter),
            focusKeyword: self::str($flat['focusKeyword'] ?? $flat['focus_keyword'] ?? null),
            secondaryKeywords: self::strings($flat['secondaryKeywords'] ?? $flat['secondary_keywords'] ?? null),
            extra: is_array($flat['extra'] ?? null) ? $flat['extra'] : [],
        );
    }

    /**
     * @return list<RobotsRule>
     */
    private static function robots(mixed $value): array
    {
        if ($value instanceof RobotsRule) {
            return [$value];
        }

        if (! is_array($value)) {
            return is_string($value) ? self::robots(explode(',', $value)) : [];
        }

        $rules = [];

        foreach ($value as $entry) {
            if ($entry instanceof RobotsRule) {
                $rules[] = $entry;

                continue;
            }

            if (! is_string($entry)) {
                continue;
            }

            // Accepts both "noindex" and "max-snippet:50".
            [$name, $argument] = array_pad(explode(':', trim($entry), 2), 2, null);
            $directive = RobotsDirective::tryFrom((string) $name);

            if ($directive === null) {
                continue;
            }

            $rules[] = new RobotsRule(
                $directive,
                $directive->requiresValue() ? ($argument ?? '') : null,
            );
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function openGraph(array $values): OpenGraphData
    {
        return new OpenGraphData(
            title: self::str($values['title'] ?? null),
            description: self::str($values['description'] ?? null),
            image: self::str($values['image'] ?? null),
            imageAlt: self::str($values['imageAlt'] ?? $values['image_alt'] ?? null),
            imageWidth: isset($values['imageWidth']) ? (int) $values['imageWidth'] : null,
            imageHeight: isset($values['imageHeight']) ? (int) $values['imageHeight'] : null,
            type: self::str($values['type'] ?? null),
            url: self::str($values['url'] ?? null),
            siteName: self::str($values['siteName'] ?? $values['site_name'] ?? null),
            locale: self::str($values['locale'] ?? null),
            alternateLocales: self::strings($values['alternateLocales'] ?? null),
            publishedTime: self::str($values['publishedTime'] ?? $values['published_time'] ?? null),
            modifiedTime: self::str($values['modifiedTime'] ?? $values['modified_time'] ?? null),
            author: self::str($values['author'] ?? null),
            section: self::str($values['section'] ?? null),
            tags: self::strings($values['tags'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function twitter(array $values): TwitterData
    {
        $card = $values['card'] ?? null;

        return new TwitterData(
            card: match (true) {
                $card instanceof TwitterCard => $card,
                is_string($card) => TwitterCard::tryFrom($card),
                default => null,
            },
            site: self::str($values['site'] ?? null),
            creator: self::str($values['creator'] ?? null),
            title: self::str($values['title'] ?? null),
            description: self::str($values['description'] ?? null),
            image: self::str($values['image'] ?? null),
            imageAlt: self::str($values['imageAlt'] ?? $values['image_alt'] ?? null),
        );
    }

    private static function str(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value) !== '' ? $value : null;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $v): string => is_string($v) ? trim($v) : '', $value),
            static fn (string $v): bool => $v !== '',
        ));
    }
}
