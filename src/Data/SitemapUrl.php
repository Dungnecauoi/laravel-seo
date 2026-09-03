<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use DateTimeInterface;
use Duxbo\Seo\Data\Concerns\Copyable;
use Duxbo\Seo\Enums\ChangeFrequency;

/**
 * One `<url>` entry.
 */
final class SitemapUrl
{
    use Copyable;

    /**
     * @param  array<string, string>  $alternates  locale => URL, for hreflang.
     * @param  list<string>  $images  Image URLs to declare on this page.
     * @param  list<SitemapVideo>  $videos
     */
    public function __construct(
        public readonly string $loc,
        public readonly ?DateTimeInterface $lastModified = null,
        public readonly ?ChangeFrequency $changeFrequency = null,
        public readonly ?float $priority = null,
        public readonly array $alternates = [],
        public readonly array $images = [],
        public readonly array $videos = [],
        public readonly ?SitemapNews $news = null,
    ) {
    }

    public static function make(string $loc): self
    {
        return new self($loc);
    }

    /**
     * @return array<string, mixed>
     */
    protected function constructorArgs(): array
    {
        return [
            'loc' => $this->loc,
            'lastModified' => $this->lastModified,
            'changeFrequency' => $this->changeFrequency,
            'priority' => $this->priority,
            'alternates' => $this->alternates,
            'images' => $this->images,
            'videos' => $this->videos,
            'news' => $this->news,
        ];
    }
}
