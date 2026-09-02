<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

final class Heading
{
    /**
     * @param  int  $level  1 for h1 through 6 for h6.
     */
    public function __construct(
        public readonly int $level,
        public readonly string $text,
    ) {
    }
}
