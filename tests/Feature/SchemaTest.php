<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Schema\Providers\OrganizationProvider;
use Duxbo\Seo\Tests\Fixtures\Article;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.schema.organization', [
            'type' => 'Organization',
            'name' => 'Công Ty Của Tôi',
            'logo' => '/storage/logo.png',
            'sameAs' => ['https://facebook.com/example'],
        ]);
    }

    public function test_nodes_are_flat_and_linked_by_id(): void
    {
        $graph = Seo::schema($this->article())->toArray();

        $types = array_column($graph['@graph'], '@type');

        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('WebPage', $types);
        $this->assertContains('Article', $types);

        $webpage = $this->node($graph, 'WebPage');

        // A reference, not a nested copy — this is what lets Google connect
        // nodes without every one repeating the site's details.
        $this->assertSame(
            ['@id' => 'http://localhost/#website'],
            $webpage['isPartOf'],
        );
    }

    public function test_the_graph_has_no_dangling_references(): void
    {
        $this->assertSame([], Seo::schema($this->article())->danglingReferences());
    }

    public function test_references_to_an_absent_provider_are_pruned_not_left_dangling(): void
    {
        // No organisation name configured, so no Organization node is emitted.
        config(['seo.schema.organization.name' => null]);

        $graph = Seo::schema($this->article());

        $this->assertSame([], $graph->danglingReferences());

        $article = $this->node($graph->toArray(), 'Article');

        // The publisher link pointed at a node that never appeared, so it is
        // dropped rather than naming something that does not exist.
        $this->assertArrayNotHasKey('publisher', $article);
    }

    public function test_a_model_node_is_wired_to_the_page_and_publisher(): void
    {
        $article = $this->node(Seo::schema($this->article())->toArray(), 'Article');

        $this->assertSame(
            ['@id' => 'https://trangcuatoi.vn/bai-viet/bai-viet-mau#webpage'],
            $article['mainEntityOfPage'],
        );
        $this->assertSame(
            ['@id' => 'http://localhost/#organization'],
            $article['publisher'],
        );
    }

    public function test_dates_are_rewritten_to_iso_8601(): void
    {
        $article = $this->node(Seo::schema($this->article())->toArray(), 'Article');

        // Google reads a malformed date as a missing field and silently drops
        // the rich result, so this conversion is not cosmetic.
        $this->assertMatchesRegularExpression(
            '/^2026-03-17T09:30:00[+\-]\d{2}:\d{2}$/',
            $article['datePublished'],
        );
    }

    public function test_relative_urls_are_made_absolute(): void
    {
        $article = $this->node(Seo::schema($this->article())->toArray(), 'Article');

        $this->assertSame('http://localhost/storage/anh.jpg', $article['image']);
    }

    public function test_null_properties_are_dropped_rather_than_emitted(): void
    {
        $article = $this->node(Seo::schema($this->article())->toArray(), 'Article');

        // The fixture returns 'wordCount' => null. An explicit null reads as a
        // malformed node, which is worse than an absent property.
        $this->assertArrayNotHasKey('wordCount', $article);
    }

    public function test_the_last_breadcrumb_carries_no_link(): void
    {
        $breadcrumb = $this->node(Seo::schema($this->article())->toArray(), 'BreadcrumbList');
        $items = $breadcrumb['itemListElement'];

        $this->assertCount(3, $items);
        $this->assertSame('http://localhost', $items[0]['item']);
        $this->assertSame(1, $items[0]['position']);

        // Google flags a self-referential final crumb as an error.
        $this->assertArrayNotHasKey('item', $items[2]);
    }

    public function test_a_model_without_breadcrumbs_emits_no_breadcrumb_node(): void
    {
        $graph = Seo::schema($this->makePost())->toArray();

        $this->assertNotContains('BreadcrumbList', array_column($graph['@graph'], '@type'));
    }

    public function test_a_removed_provider_stops_emitting(): void
    {
        Seo::removeSchema(OrganizationProvider::class);

        $graph = Seo::schema($this->article())->toArray();

        $this->assertNotContains('Organization', array_column($graph['@graph'], '@type'));
        $this->assertSame([], Seo::schema($this->article())->danglingReferences());
    }

    public function test_the_search_action_keeps_its_placeholder(): void
    {
        config(['seo.schema.website.search_url' => '/tim-kiem?q={search_term_string}']);

        $website = $this->node(Seo::schema($this->article())->toArray(), 'WebSite');

        $this->assertSame(
            'http://localhost/tim-kiem?q={search_term_string}',
            $website['potentialAction']['target']['urlTemplate'],
        );
    }

    public function test_the_rendered_block_cannot_be_closed_from_inside_a_value(): void
    {
        $article = $this->article(['name' => 'Tiêu đề </script><script>alert(1)</script>']);

        $html = (string) $article->seoTags();

        // The only </script> in the output is the one that closes the block.
        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringContainsString('<', $html);
    }

    public function test_meta_tags_and_json_ld_come_out_together(): void
    {
        $html = (string) $this->article()->seoTags();

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertStringContainsString('"@graph"', $html);
    }

    public function test_vietnamese_is_not_escaped_into_unicode_sequences(): void
    {
        $html = (string) $this->article()->seoTags();

        $this->assertStringContainsString('Công Ty Của Tôi', $html);
    }

    /**
     * @param  array<string, mixed>  $graph
     * @return array<string, mixed>
     */
    private function node(array $graph, string $type): array
    {
        foreach ($graph['@graph'] as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        $this->fail("No [{$type}] node in the graph.");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function article(array $attributes = []): Article
    {
        return Article::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-mau',
        ]);
    }

    private function makePost(): Post
    {
        return Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
    }
}
