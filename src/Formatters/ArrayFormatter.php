<?php

declare(strict_types=1);

namespace Duxbo\Seo\Formatters;

use Duxbo\Seo\Contracts\OutputFormatter;
use Duxbo\Seo\Data\SeoContext;

/**
 * Plain array output, for API responses and for further processing in PHP.
 */
final class ArrayFormatter implements OutputFormatter
{
    public function name(): string
    {
        return 'array';
    }

    /**
     * @return array<string, mixed>
     */
    public function format(SeoContext $context): array
    {
        return [
            'url' => $context->url,
            'locale' => $context->locale,
        ] + $context->data->toArray();
    }
}
