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
