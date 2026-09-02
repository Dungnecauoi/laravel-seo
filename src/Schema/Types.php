<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema;

/**
 * Shorthand for the schema.org types whose nesting is easy to get wrong.
 *
 * Not a class per type — a model returning a plain array already works, and
 * generating hundreds of classes for a vocabulary that is mostly flat key-value
 * pairs is how a schema library ends up an 800-class dependency. These are the
 * shapes where the structure, not the field names, is the hard part: a price
 * that has to sit inside an Offer, an FAQ whose answers are their own nodes.
 *
 * Every one returns a plain array, so it composes with hand-written keys.
 */
final class Types
{
    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function product(
        string $name,
        int|float|string|null $price = null,
        string $currency = 'VND',
        ?string $availability = 'InStock',
        array $extra = [],
    ): array {
        $node = ['@type' => 'Product', 'name' => $name];

        if ($price !== null) {
            // Price belongs inside an Offer, never directly on the Product —
            // the single most common mistake in product markup.
            $node['offers'] = self::offer($price, $currency, $availability);
        }

        return $node + $extra;
    }

    /**
     * @return array<string, mixed>
     */
    public static function offer(
        int|float|string $price,
        string $currency = 'VND',
        ?string $availability = 'InStock',
        ?string $url = null,
        ?string $validUntil = null,
    ): array {
        return array_filter([
            '@type' => 'Offer',
            // A string, and without thousands separators: Google rejects
            // "1.200.000" and reads a float's locale formatting unreliably.
            'price' => is_string($price) ? $price : (string) $price,
            'priceCurrency' => $currency,
            'availability' => $availability !== null ? 'https://schema.org/'.$availability : null,
            'url' => $url,
            'priceValidUntil' => $validUntil,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public static function aggregateRating(int|float $value, int $count, int|float $best = 5): array
    {
        return [
            '@type' => 'AggregateRating',
            'ratingValue' => (string) $value,
            'ratingCount' => $count,
            'bestRating' => (string) $best,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function review(string $author, int|float $rating, ?string $body = null, int|float $best = 5): array
    {
        return array_filter([
            '@type' => 'Review',
            'author' => self::person($author),
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => (string) $rating,
                'bestRating' => (string) $best,
            ],
            'reviewBody' => $body,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * @param  array<string, string>  $questions  Question text => answer HTML or text.
     * @return array<string, mixed>
     */
    public static function faq(array $questions): array
    {
        $entities = [];

        foreach ($questions as $question => $answer) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function person(string $name, ?string $url = null, ?string $image = null): array
    {
        return array_filter([
            '@type' => 'Person',
            'name' => $name,
            'url' => $url,
            'image' => $image,
        ], static fn (mixed $v): bool => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function video(
        string $name,
        string $thumbnailUrl,
        string $uploadDate,
        ?string $description = null,
        ?string $contentUrl = null,
        ?string $embedUrl = null,
        ?string $duration = null,
        array $extra = [],
    ): array {
        return array_filter([
            '@type' => 'VideoObject',
            'name' => $name,
            'description' => $description,
            'thumbnailUrl' => $thumbnailUrl,
            'uploadDate' => $uploadDate,
            'contentUrl' => $contentUrl,
            'embedUrl' => $embedUrl,
            // ISO 8601 duration: PT1M33S, not "1:33".
            'duration' => $duration,
        ], static fn (mixed $v): bool => $v !== null) + $extra;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function event(
        string $name,
        string $startDate,
        ?string $endDate = null,
        ?string $locationName = null,
        ?string $locationAddress = null,
        array $extra = [],
    ): array {
        $node = array_filter([
            '@type' => 'Event',
            'name' => $name,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ], static fn (mixed $v): bool => $v !== null);

        if ($locationName !== null || $locationAddress !== null) {
            $node['location'] = array_filter([
                '@type' => 'Place',
                'name' => $locationName,
                'address' => $locationAddress,
            ], static fn (mixed $v): bool => $v !== null);
        }

        return $node + $extra;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function jobPosting(
        string $title,
        string $description,
        string $datePosted,
        ?string $employmentType = null,
        ?string $hiringOrganization = null,
        ?string $locality = null,
        array $extra = [],
    ): array {
        $node = array_filter([
            '@type' => 'JobPosting',
            'title' => $title,
            'description' => $description,
            'datePosted' => $datePosted,
            'employmentType' => $employmentType,
        ], static fn (mixed $v): bool => $v !== null);

        if ($hiringOrganization !== null) {
            $node['hiringOrganization'] = ['@type' => 'Organization', 'name' => $hiringOrganization];
        }

        if ($locality !== null) {
            $node['jobLocation'] = [
                '@type' => 'Place',
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $locality],
            ];
        }

        return $node + $extra;
    }

    /**
     * @param  list<string>  $steps
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function howTo(string $name, array $steps, array $extra = []): array
    {
        $elements = [];
        $position = 1;

        foreach ($steps as $step) {
            $elements[] = [
                '@type' => 'HowToStep',
                'position' => $position++,
                'text' => $step,
            ];
        }

        return ['@type' => 'HowTo', 'name' => $name, 'step' => $elements] + $extra;
    }
}
