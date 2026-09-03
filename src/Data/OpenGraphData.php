<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Data\Concerns\Copyable;

/**
 * Open Graph properties, as consumed by Facebook, Zalo, Slack and most others.
 */
final class OpenGraphData
{
    use Copyable;

    /**
     * @param  list<string>  $alternateLocales  Other locales this page exists in.
     * @param  string|null  $publishedTime  ISO 8601. `article:published_time` — only meaningful when `type` is 'article'.
     * @param  string|null  $modifiedTime  ISO 8601. `article:modified_time`.
     * @param  string|null  $author  `article:author` — a profile URL per the OG spec, though most consumers accept a plain name.
     * @param  string|null  $section  `article:section` — the category this content sits under.
     * @param  list<string>  $tags  `article:tag`, repeated once per entry.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly ?string $imageAlt = null,
        public readonly ?int $imageWidth = null,
        public readonly ?int $imageHeight = null,
        public readonly ?string $type = null,
        public readonly ?string $url = null,
        public readonly ?string $siteName = null,
        public readonly ?string $locale = null,
        public readonly array $alternateLocales = [],
        public readonly ?string $publishedTime = null,
        public readonly ?string $modifiedTime = null,
        public readonly ?string $author = null,
        public readonly ?string $section = null,
        public readonly array $tags = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->title === null
            && $this->description === null
            && $this->image === null
            && $this->type === null
            && $this->url === null;
    }

    public function isArticle(): bool
    {
        return $this->type === 'article';
    }

    /**
     * @return array<string, mixed>
     */
    protected function constructorArgs(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'imageAlt' => $this->imageAlt,
            'imageWidth' => $this->imageWidth,
            'imageHeight' => $this->imageHeight,
            'type' => $this->type,
            'url' => $this->url,
            'siteName' => $this->siteName,
            'locale' => $this->locale,
            'alternateLocales' => $this->alternateLocales,
            'publishedTime' => $this->publishedTime,
            'modifiedTime' => $this->modifiedTime,
            'author' => $this->author,
            'section' => $this->section,
            'tags' => $this->tags,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            $this->constructorArgs(),
            static fn (mixed $value): bool => $value !== null && $value !== [],
        );
    }
}
