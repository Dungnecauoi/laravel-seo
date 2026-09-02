<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

final class Link
{
    public function __construct(
        public readonly string $href,
        public readonly string $text = '',
        public readonly bool $internal = true,
        public readonly bool $noFollow = false,
    ) {
    }
}
