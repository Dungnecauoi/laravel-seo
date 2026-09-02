<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\SeoContext;

/**
 * Builds one node of the JSON-LD `@graph`.
 *
 * Nodes are flat and reference each other by `@id` rather than nesting, which
 * is how Google reads context — and how a provider can point at a node another
 * provider contributed without knowing anything about it.
 */
interface SchemaProvider
{
    /**
     * Whether this provider has anything to say about the current page.
     */
    public function supports(SeoContext $context): bool;

    /**
     * The node's `@id`, unique within the graph.
     *
     * Conventionally the page URL plus a fragment: `https://site.vn/#organization`.
     */
    public function id(SeoContext $context): string;

    /**
     * The node itself, including `@type` and `@id`.
     *
     * @return array<string, mixed>
     */
    public function build(SeoContext $context): array;

    /**
     * Lower runs first. Providers other nodes reference should sort early so
     * their `@id` is already registered when a referrer is built.
     */
    public function priority(): int;
}
