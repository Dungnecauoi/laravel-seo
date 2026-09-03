<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Data\OpenGraphData;
use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * article:* only means anything to Facebook, LinkedIn and friends under
 * og:type=article — a 'website' page emitting it is spec-invalid and ignored
 * by every real parser anyway.
 */
final class OpenGraphArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_tags_render_for_an_article_type_page(): void
    {
        $post = $this->makePost();
        $post->saveSeo([
            'og.type' => 'article',
            'og.publishedTime' => '2026-03-17T09:30:00+00:00',
            'og.modifiedTime' => '2026-03-18T10:00:00+00:00',
            'og.author' => 'Nguyễn Văn A',
            'og.section' => 'Công nghệ',
            'og.tags' => ['seo', 'laravel'],
        ]);

        $html = (string) $post->fresh()->seoTags();

        $this->assertStringContainsString(
            '<meta property="article:published_time" content="2026-03-17T09:30:00+00:00">',
            $html,
        );
        $this->assertStringContainsString('<meta property="article:modified_time"', $html);
        $this->assertStringContainsString('<meta property="article:author" content="Nguyễn Văn A">', $html);
        $this->assertStringContainsString('<meta property="article:section" content="Công nghệ">', $html);
        $this->assertStringContainsString('<meta property="article:tag" content="seo">', $html);
        $this->assertStringContainsString('<meta property="article:tag" content="laravel">', $html);
    }

    public function test_article_tags_are_absent_for_a_website_type_page(): void
    {
        $post = $this->makePost();
        $post->saveSeo([
            'og.type' => 'website',
            'og.publishedTime' => '2026-03-17T09:30:00+00:00',
        ]);

        $html = (string) $post->fresh()->seoTags();

        // Meaningless outside og:type=article per the Open Graph spec, and
        // every real consumer ignores it there regardless.
        $this->assertStringNotContainsString('article:published_time', $html);
    }

    public function test_the_next_formatter_nests_article_fields_under_open_graph(): void
    {
        $post = $this->makePost();
        $post->saveSeo([
            'og.type' => 'article',
            'og.publishedTime' => '2026-03-17T09:30:00+00:00',
            'og.author' => 'Nguyễn Văn A',
            'og.tags' => ['seo'],
        ]);

        /** @var array<string, mixed> $metadata */
        $metadata = Seo::format('next', $post->fresh()->seoContext());

        $this->assertSame('2026-03-17T09:30:00+00:00', $metadata['openGraph']['publishedTime']);
        $this->assertSame(['Nguyễn Văn A'], $metadata['openGraph']['authors']);
        $this->assertSame(['seo'], $metadata['openGraph']['tags']);
    }

    public function test_article_fields_survive_a_storage_round_trip(): void
    {
        $post = $this->makePost();
        $post->saveSeo(new \Duxbo\Seo\Data\SeoData(openGraph: new OpenGraphData(
            type: 'article',
            publishedTime: '2026-03-17T09:30:00+00:00',
            section: 'Công nghệ',
            tags: ['a', 'b'],
        )));

        $stored = app(\Duxbo\Seo\Contracts\MetadataRepository::class)->find($post->fresh());

        $this->assertSame('2026-03-17T09:30:00+00:00', $stored?->openGraph?->publishedTime);
        $this->assertSame('Công nghệ', $stored?->openGraph?->section);
        $this->assertSame(['a', 'b'], $stored?->openGraph?->tags);
    }

    private function makePost(): Post
    {
        return Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
    }
}
