<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Data\SchemaGraph;
use PHPUnit\Framework\TestCase;

final class SchemaGraphTest extends TestCase
{
    public function test_nodes_are_flat_and_carry_their_own_id(): void
    {
        $graph = (new SchemaGraph())->add('https://site.vn/#organization', ['@type' => 'Organization']);

        $output = $graph->toArray();

        $this->assertSame('https://schema.org', $output['@context']);
        $this->assertSame([
            ['@type' => 'Organization', '@id' => 'https://site.vn/#organization'],
        ], $output['@graph']);
    }

    public function test_a_reference_to_a_missing_node_is_reported(): void
    {
        $graph = (new SchemaGraph())->add('https://site.vn/#website', [
            '@type' => 'WebSite',
            'publisher' => ['@id' => 'https://site.vn/#organization'],
        ]);

        $this->assertSame(['https://site.vn/#organization'], $graph->danglingReferences());
    }

    public function test_a_satisfied_reference_is_not_reported(): void
    {
        $graph = (new SchemaGraph())
            ->add('https://site.vn/#organization', ['@type' => 'Organization'])
            ->add('https://site.vn/#website', [
                '@type' => 'WebSite',
                'publisher' => ['@id' => 'https://site.vn/#organization'],
            ]);

        $this->assertSame([], $graph->danglingReferences());
    }

    public function test_optional_references_are_omitted_rather_than_left_dangling(): void
    {
        $graph = new SchemaGraph();

        $this->assertNull($graph->refIfPresent('https://site.vn/#breadcrumb'));

        $graph->add('https://site.vn/#breadcrumb', ['@type' => 'BreadcrumbList']);

        $this->assertSame(
            ['@id' => 'https://site.vn/#breadcrumb'],
            $graph->refIfPresent('https://site.vn/#breadcrumb'),
        );
    }
}
