<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * Another record whose stored title or description exactly matches.
 */
final class DuplicateMatch
{
    public function __construct(
        public readonly string $seoType,
        public readonly string $seoKey,
        public readonly ?string $locale,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->seoType,
            'id' => $this->seoKey,
            'locale' => $this->locale,
        ];
    }
}
