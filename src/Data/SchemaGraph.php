<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * The JSON-LD `@graph` under construction.
 *
 * Nodes stay flat and point at one another by `@id`. Mutable by design: it is
 * assembled once per request by a run of providers, then rendered. Making it
 * immutable would mean copying the whole graph per node for no benefit.
 */
final class SchemaGraph
{
    /** @var array<string, array<string, mixed>> */
    private array $nodes = [];

    /**
     * @param  array<string, mixed>  $node  Must include `@type`; `@id` is filled in from $id.
     */
    public function add(string $id, array $node): self
    {
        $node['@id'] = $id;
        $this->nodes[$id] = $node;

        return $this;
    }

    public function has(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * A reference to another node: `['@id' => '…']`.
     *
     * @return array{'@id': string}
     */
    public function ref(string $id): array
    {
        return ['@id' => $id];
    }

    /**
     * Reference a node only if it exists, so an optional provider being absent
     * does not leave a dangling `@id` in the output.
     *
     * @return array{'@id': string}|null
     */
    public function refIfPresent(string $id): ?array
    {
        return $this->has($id) ? $this->ref($id) : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    /**
     * `@id` values referenced by some node but never registered.
     *
     * Dangling references are the classic bug in a hand-assembled graph, so
     * this is asserted in tests and surfaced by `seo:doctor`.
     *
     * @return list<string>
     */
    public function danglingReferences(): array
    {
        $referenced = [];

        array_walk_recursive(
            $this->nodes,
            function (mixed $value, int|string $key) use (&$referenced): void {
                if ($key === '@id' && is_string($value)) {
                    $referenced[] = $value;
                }
            },
        );

        $missing = array_diff(array_unique($referenced), array_keys($this->nodes));

        return array_values($missing);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $context = 'https://schema.org'): array
    {
        return [
            '@context' => $context,
            '@graph' => array_values($this->nodes),
        ];
    }
}
