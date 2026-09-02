<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Unit;

use Duxbo\Seo\Data\SchemaGraph;
use Duxbo\Seo\Schema\SchemaValidator;
use Duxbo\Seo\Schema\Types;
use PHPUnit\Framework\TestCase;

final class SchemaTypesTest extends TestCase
{
    public function test_a_price_is_placed_inside_an_offer_not_on_the_product(): void
    {
        $product = Types::product('Áo thun', 199000);

        // The most common mistake in product markup, and the reason this
        // helper exists at all.
        $this->assertArrayNotHasKey('price', $product);
        $this->assertSame('Offer', $product['offers']['@type']);
        $this->assertSame('199000', $product['offers']['price']);
    }

    public function test_a_price_is_emitted_as_a_string_without_separators(): void
    {
        // Google rejects "1.200.000" and reads locale-formatted floats
        // unreliably, so the value is always a bare numeric string.
        $this->assertSame('1200000', Types::offer(1200000)['price']);
        $this->assertSame('199.5', Types::offer(199.5)['price']);
    }

    public function test_availability_is_expanded_to_a_full_schema_url(): void
    {
        $this->assertSame(
            'https://schema.org/OutOfStock',
            Types::offer(100, 'VND', 'OutOfStock')['availability'],
        );
    }

    public function test_faq_answers_become_their_own_nodes(): void
    {
        $faq = Types::faq([
            'Giao hàng bao lâu?' => 'Từ 2 đến 3 ngày.',
            'Có đổi trả không?' => 'Trong 7 ngày.',
        ]);

        $this->assertSame('FAQPage', $faq['@type']);
        $this->assertCount(2, $faq['mainEntity']);
        $this->assertSame('Question', $faq['mainEntity'][0]['@type']);
        $this->assertSame('Answer', $faq['mainEntity'][0]['acceptedAnswer']['@type']);
        $this->assertSame('Từ 2 đến 3 ngày.', $faq['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_how_to_steps_are_numbered(): void
    {
        $howTo = Types::howTo('Cài đặt', ['Tải về', 'Chạy lệnh', 'Xong']);

        $this->assertSame([1, 2, 3], array_column($howTo['step'], 'position'));
    }

    public function test_the_validator_reports_a_missing_required_property(): void
    {
        $graph = (new SchemaGraph())->add('https://site.vn/#article', [
            '@type' => 'Article',
            'description' => 'Không có headline',
        ]);

        $problems = (new SchemaValidator())->validate($graph);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('Article is missing required property [headline]', $problems[0]);
    }

    public function test_the_validator_looks_inside_nested_nodes(): void
    {
        $graph = (new SchemaGraph())->add('https://site.vn/#product', [
            '@type' => 'Product',
            'name' => 'Áo thun',
            // An Offer without a currency: where price errors actually live.
            'offers' => ['@type' => 'Offer', 'price' => '199000'],
        ]);

        $problems = (new SchemaValidator())->validate($graph);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('[priceCurrency]', $problems[0]);
    }

    public function test_the_validator_reports_a_reference_to_a_missing_node(): void
    {
        $graph = (new SchemaGraph())->add('https://site.vn/#website', [
            '@type' => 'WebSite',
            'url' => 'https://site.vn/',
            'publisher' => ['@id' => 'https://site.vn/#organization'],
        ]);

        $problems = (new SchemaValidator())->validate($graph);

        $this->assertSame(
            ['reference to [https://site.vn/#organization], which is not in the graph'],
            $problems,
        );
    }

    public function test_a_sound_graph_reports_nothing(): void
    {
        $graph = (new SchemaGraph())
            ->add('https://site.vn/#organization', ['@type' => 'Organization', 'name' => 'Công Ty'])
            ->add('https://site.vn/#website', [
                '@type' => 'WebSite',
                'url' => 'https://site.vn/',
                'publisher' => ['@id' => 'https://site.vn/#organization'],
            ]);

        $this->assertSame([], (new SchemaValidator())->validate($graph));
    }

    public function test_a_node_that_declares_its_own_id_is_not_a_dangling_reference(): void
    {
        // A nested ImageObject naming itself is a definition, not a reference.
        // Conflating the two flagged every correctly-built graph.
        $graph = (new SchemaGraph())->add('https://site.vn/#organization', [
            '@type' => 'Organization',
            'name' => 'Công Ty',
            'logo' => [
                '@type' => 'ImageObject',
                '@id' => 'https://site.vn/#logo',
                'url' => 'https://site.vn/logo.png',
            ],
        ]);

        $this->assertSame([], $graph->danglingReferences());
    }
}
