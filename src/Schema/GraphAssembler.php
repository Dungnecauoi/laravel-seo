<?php

declare(strict_types=1);

namespace Duxbo\Seo\Schema;

use Duxbo\Seo\Contracts\SchemaProvider;
use Duxbo\Seo\Data\SchemaGraph;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Contracts\Container\Container;

/**
 * Runs the registered providers and assembles one `@graph`.
 *
 * Nodes stay flat and reference each other by `@id` rather than nesting.
 * Google reads context from those links, and it means a provider can point at
 * a node another provider contributed without knowing anything about it.
 */
final class GraphAssembler
{
    /** @var list<SchemaProvider> */
    private array $providers = [];

    public function __construct(
        private readonly Container $container,
        private readonly SchemaNormalizer $normalizer,
    ) {
    }

    /**
     * @param  class-string<SchemaProvider>|SchemaProvider  $provider
     */
    public function register(string|SchemaProvider $provider): self
    {
        $this->providers[] = is_string($provider)
            ? $this->container->make($provider)
            : $provider;

        return $this;
    }

    /**
     * Remove a provider by class name — how a built-in gets replaced.
     *
     * @param  class-string<SchemaProvider>  $provider
     */
    public function remove(string $provider): self
    {
        $this->providers = array_values(array_filter(
            $this->providers,
            static fn (SchemaProvider $registered): bool => ! $registered instanceof $provider,
        ));

        return $this;
    }

    /**
     * @return list<SchemaProvider>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    public function build(SeoContext $context): SchemaGraph
    {
        $graph = new SchemaGraph();

        $providers = $this->providers;

        // Sorted so a node is registered before anything that references it.
        usort(
            $providers,
            static fn (SchemaProvider $a, SchemaProvider $b): int => $a->priority() <=> $b->priority(),
        );

        foreach ($providers as $provider) {
            if (! $provider->supports($context)) {
                continue;
            }

            $node = $provider->build($context);

            if ($node === []) {
                continue;
            }

            // A provider may return several nodes by nesting them in a list.
            foreach ($this->nodesOf($node) as $index => $single) {
                if ($single === []) {
                    continue;
                }

                // A node that names its own `@id` keeps it. That is how a
                // provider contributes a node other nodes reference by a
                // stable identifier — an organisation's logo, say — instead of
                // being forced into a generated one.
                $declared = $single['@id'] ?? null;

                if (is_string($declared) && $declared !== '') {
                    $graph->add($declared, $this->normalizer->normalize($single));

                    continue;
                }

                $id = $provider->id($context);

                if ($index > 0) {
                    $id .= '-'.($index + 1);
                }

                $graph->add($id, $this->normalizer->normalize($single));
            }
        }

        return $this->pruneDanglingReferences($graph, $context);
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function nodesOf(array $node): array
    {
        // A list of nodes rather than one node: every element is an array and
        // the keys are sequential.
        if (isset($node[0]) && is_array($node[0])) {
            /** @var list<array<string, mixed>> $nodes */
            $nodes = array_values($node);

            return $nodes;
        }

        /** @var array<string, mixed> $node */
        return [$node];
    }

    /**
     * Strip references to nodes that were never registered.
     *
     * A dangling `@id` is the classic bug in a hand-assembled graph: an
     * optional provider sits out, and every node that pointed at it now names
     * something that does not exist. Google reports this as an error on the
     * referring node, not the missing one, so it is hard to trace back.
     */
    private function pruneDanglingReferences(SchemaGraph $graph, SeoContext $context): SchemaGraph
    {
        $dangling = $graph->danglingReferences();

        if ($dangling === []) {
            return $graph;
        }

        $pruned = new SchemaGraph();

        foreach ($graph->nodes() as $id => $node) {
            $pruned->add($id, $this->stripReferences($node, $dangling, $id));
        }

        return $pruned;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @param  list<string>  $dangling
     * @return array<array-key, mixed>
     */
    private function stripReferences(array $node, array $dangling, string $selfId): array
    {
        $clean = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $isBareReference = array_keys($value) === ['@id'];

                if ($isBareReference && in_array($value['@id'], $dangling, true)) {
                    continue;
                }

                $value = $this->stripReferences($value, $dangling, $selfId);

                if ($value === []) {
                    continue;
                }
            }

            // The node's own @id is never a dangling reference to itself.
            if ($key === '@id' && $value === $selfId) {
                $clean[$key] = $value;

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
