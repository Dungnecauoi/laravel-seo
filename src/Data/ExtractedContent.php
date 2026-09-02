<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * Structured view of a page's body, produced by a ContentExtractor.
 */
final class ExtractedContent
{
    /**
     * @param  list<Heading>  $headings
     * @param  list<Link>  $links
     * @param  list<ImageRef>  $images
     */
    public function __construct(
        public readonly string $plainText,
        public readonly array $headings = [],
        public readonly array $links = [],
        public readonly array $images = [],
    ) {
    }

    public static function empty(): self
    {
        return new self('');
    }

    /**
     * @return list<Heading>
     */
    public function headingsOfLevel(int ...$levels): array
    {
        return array_values(array_filter(
            $this->headings,
            static fn (Heading $h): bool => in_array($h->level, $levels, true),
        ));
    }

    /**
     * @return list<Link>
     */
    public function internalLinks(): array
    {
        return array_values(array_filter($this->links, static fn (Link $l): bool => $l->internal));
    }

    /**
     * @return list<Link>
     */
    public function externalLinks(): array
    {
        return array_values(array_filter($this->links, static fn (Link $l): bool => ! $l->internal));
    }

    /**
     * @return list<ImageRef>
     */
    public function imagesMissingAlt(): array
    {
        return array_values(array_filter(
            $this->images,
            static fn (ImageRef $i): bool => $i->alt === null || trim($i->alt) === '',
        ));
    }
}
