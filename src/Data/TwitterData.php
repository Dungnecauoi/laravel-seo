<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Data\Concerns\Copyable;
use Duxbo\Seo\Enums\TwitterCard;

final class TwitterData
{
    use Copyable;

    public function __construct(
        public readonly ?TwitterCard $card = null,
        public readonly ?string $site = null,
        public readonly ?string $creator = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $image = null,
        public readonly ?string $imageAlt = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return array_filter(
            $this->constructorArgs(),
            static fn (mixed $value): bool => $value !== null,
        ) === [];
    }

    /**
     * Field by field, not the whole object — see
     * {@see OpenGraphData::fillMissingFrom()} for why: a record that maps
     * only `twitter.title` must still be able to fall back to a site-wide
     * default `twitter.image` from a later pipeline stage.
     */
    public function fillMissingFrom(self $fallback): self
    {
        return new self(
            card: $this->card ?? $fallback->card,
            site: $this->site ?? $fallback->site,
            creator: $this->creator ?? $fallback->creator,
            title: $this->title ?? $fallback->title,
            description: $this->description ?? $fallback->description,
            image: $this->image ?? $fallback->image,
            imageAlt: $this->imageAlt ?? $fallback->imageAlt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function constructorArgs(): array
    {
        return [
            'card' => $this->card,
            'site' => $this->site,
            'creator' => $this->creator,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'imageAlt' => $this->imageAlt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $args = $this->constructorArgs();
        $args['card'] = $this->card?->value;

        return array_filter($args, static fn (mixed $value): bool => $value !== null);
    }
}
