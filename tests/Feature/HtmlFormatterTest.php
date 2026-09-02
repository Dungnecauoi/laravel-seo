<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class HtmlFormatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_core_tags(): void
    {
        $post = $this->makePost(['name' => 'Bài viết mẫu', 'excerpt' => 'Mô tả ngắn']);

        $html = (string) $post->seoTags();

        $this->assertStringContainsString('<title>Bài viết mẫu</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Mô tả ngắn">', $html);
        $this->assertStringContainsString(
            '<link rel="canonical" href="https://trangcuatoi.vn/bai-viet/bai-viet-mau">',
            $html,
        );
    }

    public function test_quotes_in_a_title_cannot_break_out_of_an_attribute(): void
    {
        $post = $this->makePost([
            'name' => 'Tiêu " đề',
            'excerpt' => '"><script>alert(1)</script>',
        ]);

        $html = (string) $post->seoTags();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function test_open_graph_falls_back_to_the_page_title_and_description(): void
    {
        $post = $this->makePost(['name' => 'Bài viết mẫu', 'excerpt' => 'Mô tả ngắn']);

        $html = (string) $post->seoTags();

        // A link with no preview is worse than a plain one, so og:title is
        // emitted from the page title rather than omitted.
        $this->assertStringContainsString('<meta property="og:title" content="Bài viết mẫu">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Mô tả ngắn">', $html);
    }

    public function test_a_relative_open_graph_image_is_made_absolute(): void
    {
        $post = $this->makePost(['cover_url' => '/storage/anh.jpg']);

        $html = (string) $post->seoTags();

        $this->assertMatchesRegularExpression(
            '#<meta property="og:image" content="https?://[^/]+/storage/anh\.jpg">#',
            $html,
        );
    }

    public function test_a_single_locale_site_emits_no_hreflang(): void
    {
        config(['seo.locales.supported' => ['vi']]);

        $html = (string) $this->makePost()->seoTags();

        // A lone self-referential hreflang tends to get the cluster ignored.
        $this->assertStringNotContainsString('hreflang', $html);
    }

    public function test_multiple_locales_emit_alternates_and_x_default(): void
    {
        config(['seo.locales.supported' => ['vi', 'en']]);

        $html = (string) $this->makePost()->seoTags();

        $this->assertStringContainsString('hreflang="vi"', $html);
        $this->assertStringContainsString('hreflang="en"', $html);
        $this->assertStringContainsString('hreflang="x-default"', $html);
        $this->assertStringContainsString('/en/bai-viet/bai-viet-mau', $html);
    }

    public function test_an_unknown_formatter_names_the_ones_that_exist(): void
    {
        $this->expectExceptionMessageMatches('/No SEO formatter named \[svelte\].*html, array/s');

        Seo::format('svelte', $this->makePost()->seoContext());
    }

    public function test_the_array_formatter_returns_structured_output(): void
    {
        $post = $this->makePost(['name' => 'Bài viết mẫu']);

        /** @var array<string, mixed> $output */
        $output = Seo::format('array', $post->seoContext());

        $this->assertSame('Bài viết mẫu', $output['title']);
        $this->assertSame('https://trangcuatoi.vn/bai-viet/bai-viet-mau', $output['url']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-mau',
        ]);
    }
}
