<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema;

use DateTimeInterface;
use Duxbo\Seo\Contracts\UrlGenerator;
use Illuminate\Support\Carbon;

/**
 * Cleans a hand-written node into something Google will accept.
 *
 * The three things people get wrong, every time: dates that are not ISO 8601,
 * image paths that are relative, and null values left in the output. Google
 * treats a malformed date as a missing field and silently drops the rich
 * result, with no error anywhere to explain why.
 */
final class SchemaNormalizer
{
    /**
     * Keys whose values are URLs and must be absolute.
     */
    private const URL_KEYS = [
        'url', 'image', 'logo', 'contentUrl', 'thumbnailUrl', 'embedUrl',
        'targetUrl', 'sameAs', 'item',
    ];

    /**
     * Keys whose values are dates and must be ISO 8601.
     */
    private const DATE_KEYS = [
        'datePublished', 'dateModified', 'dateCreated', 'uploadDate',
        'startDate', 'endDate', 'validFrom', 'validThrough', 'expires',
        'priceValidUntil', 'datePosted',
    ];

    public function __construct(private readonly UrlGenerator $urls)
    {
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    public function normalize(array $node): array
    {
        $clean = [];

        foreach ($node as $key => $value) {
            $value = $this->normalizeValue((string) $key, $value);

            // Drop empties: an empty string or null property is worse than an
            // absent one, since Google reads it as a malformed node.
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    private function normalizeValue(string $key, mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (in_array($key, self::DATE_KEYS, true) && is_string($value) && $value !== '') {
            return $this->toIso8601($value);
        }

        if (in_array($key, self::URL_KEYS, true)) {
            return $this->absolutize($value);
        }

        if (is_array($value)) {
            return $this->normalize($value);
        }

        return $value;
    }

    private function toIso8601(string $value): string
    {
        try {
            return Carbon::parse($value)->toAtomString();
        } catch (\Throwable) {
            // An unparseable date is left alone rather than replaced with a
            // wrong one; validation will flag it.
            return $value;
        }
    }

    private function absolutize(mixed $value): mixed
    {
        if (is_string($value) && $value !== '') {
            // A reference such as ['@id' => …] arrives as an array, not here.
            return str_starts_with($value, '#') ? $value : $this->urls->absolute($value);
        }

        if (is_array($value)) {
            // Nested nodes and @id references pass through normalize(); only a
            // plain list of URL strings is mapped.
            if (isset($value['@id']) || isset($value['@type'])) {
                return $this->normalize($value);
            }

            return array_map(fn (mixed $item): mixed => $this->absolutize($item), $value);
        }

        return $value;
    }
}
