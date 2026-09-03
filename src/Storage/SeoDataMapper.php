<?php

declare(strict_types=1);

namespace Duxbo\Seo\Storage;

use Duxbo\Seo\Data\OpenGraphData;
use Duxbo\Seo\Data\RobotsRule;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Data\TwitterData;
use Duxbo\Seo\Enums\RobotsDirective;
use Duxbo\Seo\Enums\TwitterCard;

/**
 * Translates between a stored row and the SeoData object.
 *
 * Kept apart from both the model and the repository so an alternative storage
 * backend — a JSON column, a remote CMS — can reuse the column shape without
 * inheriting the Eloquent repository.
 */
final class SeoDataMapper
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function toData(array $row): SeoData
    {
        return new SeoData(
            title: self::str($row['title'] ?? null),
            description: self::str($row['description'] ?? null),
            canonical: self::str($row['canonical_url'] ?? null),
            robots: $this->robotsFromArray(self::arr($row['robots'] ?? null)),
            openGraph: $this->openGraphFromArray(self::arr($row['og'] ?? null)),
            twitter: $this->twitterFromArray(self::arr($row['twitter'] ?? null)),
            focusKeyword: self::str($row['focus_keyword'] ?? null),
            secondaryKeywords: array_values(array_filter(
                self::arr($row['secondary_keywords'] ?? null),
                'is_string',
            )),
            score: isset($row['score']) ? (int) $row['score'] : null,
            extra: self::arr($row['extra'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(SeoData $data): array
    {
        return [
            'title' => $data->title,
            'description' => $data->description,
            'canonical_url' => $data->canonical,
            'robots' => $data->robots === [] ? null : array_map(
                static fn (RobotsRule $rule): array => [
                    'directive' => $rule->directive->value,
                    'value' => $rule->value,
                ],
                $data->robots,
            ),
            'og' => $data->openGraph?->isEmpty() === false ? $data->openGraph->toArray() : null,
            'twitter' => $data->twitter?->isEmpty() === false ? $data->twitter->toArray() : null,
            'focus_keyword' => $data->focusKeyword,
            'secondary_keywords' => $data->secondaryKeywords === [] ? null : $data->secondaryKeywords,
            'score' => $data->score,
            'extra' => $data->extra === [] ? null : $data->extra,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $stored
     * @return list<RobotsRule>
     */
    private function robotsFromArray(array $stored): array
    {
        $rules = [];

        foreach ($stored as $entry) {
            if (! is_array($entry) || ! isset($entry['directive']) || ! is_string($entry['directive'])) {
                continue;
            }

            $directive = RobotsDirective::tryFrom($entry['directive']);

            if ($directive === null) {
                // A directive written by a newer release, or removed since.
                // Dropping it beats failing to render the page at all.
                continue;
            }

            $value = $entry['value'] ?? null;

            $rules[] = new RobotsRule(
                $directive,
                is_int($value) || is_string($value) ? $value : null,
            );
        }

        return $rules;
    }

    /**
     * @param  array<array-key, mixed>  $stored
     */
    private function openGraphFromArray(array $stored): ?OpenGraphData
    {
        if ($stored === []) {
            return null;
        }

        return new OpenGraphData(
            title: self::str($stored['title'] ?? null),
            description: self::str($stored['description'] ?? null),
            image: self::str($stored['image'] ?? null),
            imageAlt: self::str($stored['imageAlt'] ?? null),
            imageWidth: isset($stored['imageWidth']) ? (int) $stored['imageWidth'] : null,
            imageHeight: isset($stored['imageHeight']) ? (int) $stored['imageHeight'] : null,
            type: self::str($stored['type'] ?? null),
            url: self::str($stored['url'] ?? null),
            siteName: self::str($stored['siteName'] ?? null),
            locale: self::str($stored['locale'] ?? null),
            alternateLocales: array_values(array_filter(
                self::arr($stored['alternateLocales'] ?? null),
                'is_string',
            )),
            publishedTime: self::str($stored['publishedTime'] ?? null),
            modifiedTime: self::str($stored['modifiedTime'] ?? null),
            author: self::str($stored['author'] ?? null),
            section: self::str($stored['section'] ?? null),
            tags: array_values(array_filter(
                self::arr($stored['tags'] ?? null),
                'is_string',
            )),
        );
    }

    /**
     * @param  array<array-key, mixed>  $stored
     */
    private function twitterFromArray(array $stored): ?TwitterData
    {
        if ($stored === []) {
            return null;
        }

        $card = self::str($stored['card'] ?? null);

        return new TwitterData(
            card: $card !== null ? TwitterCard::tryFrom($card) : null,
            site: self::str($stored['site'] ?? null),
            creator: self::str($stored['creator'] ?? null),
            title: self::str($stored['title'] ?? null),
            description: self::str($stored['description'] ?? null),
            image: self::str($stored['image'] ?? null),
            imageAlt: self::str($stored['imageAlt'] ?? null),
        );
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arr(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
