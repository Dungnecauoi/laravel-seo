<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

final class ImageRef
{
    public function __construct(
        public readonly string $src,
        public readonly ?string $alt = null,
        public readonly ?string $title = null,
    ) {
    }
}
