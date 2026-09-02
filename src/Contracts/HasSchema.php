<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\SeoContext;

/**
 * A model that describes itself as a schema.org node.
 *
 * Optional. Implement it to emit an Article, Product, Recipe — any type at
 * all — as a plain array. The assembler supplies the `@id`, wires the node to
 * the page it belongs to, and normalises dates and URLs, which is the part
 * that is fiddly to get right.
 *
 * Returning a list of arrays emits several nodes for one record.
 *
 *     public function seoSchema(SeoContext $context): array
 *     {
 *         return [
 *             '@type' => 'Article',
 *             'headline' => $this->name,
 *             'datePublished' => $this->published_at,
 *             'author' => ['@type' => 'Person', 'name' => $this->author_name],
 *         ];
 *     }
 */
interface HasSchema
{
    /**
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function seoSchema(SeoContext $context): array;
}
