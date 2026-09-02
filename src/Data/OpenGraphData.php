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
