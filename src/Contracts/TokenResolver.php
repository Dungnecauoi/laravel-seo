<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\SeoContext;

/**
 * Expands one `%token%` inside a title or description template.
 */
interface TokenResolver
{
    /**
     * Token name without the delimiters, e.g. `sitename`.
     */
    public function key(): string;

    /**
     * Returning null drops the token and tidies the surrounding whitespace,
     * so an unresolved token never reaches the rendered page.
     *
     * @param  string|null  $argument  Value inside parentheses, as in `%field(price)%`.
     */
    public function resolve(SeoContext $context, ?string $argument = null): ?string;
}
