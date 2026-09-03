<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Data\RobotsRule;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Tests\Fixtures\PlainPost;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_the_model_mapping_when_nothing_is_stored(): void
    {
        $post = $this->makePost(['name' => 'Bài viết mẫu', 'excerpt' => 'Mô tả ngắn']);

        $data = $post->seo();

        $this->assertSame('Bài viết mẫu', $data->title);
        $this->assertSame('Mô tả ngắn', $data->description);
    }

    public function test_a_stored_value_beats_the_model_mapping(): void
    {
        $post = $this->makePost(['name' => 'Tên gốc']);
        $post->saveSeo(['title' => 'Tiêu đề đã sửa']);

        $this->assertSame('Tiêu đề đã sửa', $post->fresh()->seo()->title);
    }

    public function test_a_template_applies_only_where_nothing_earlier_decided(): void
    {
        config(['seo.models.'.Post::class.'.template' => [
            'title' => '%title% %sep% %sitename%',
        ]]);

        $post = $this->makePost(['name' => 'Bài viết mẫu']);

        // The model mapping already supplied a title, so the template does not
        // get to overwrite it — it only fills what is still missing.
        $this->assertSame('Bài viết mẫu', $post->seo()->title);
    }

    public function test_the_template_wins_when_the_model_offers_nothing(): void
    {
        config(['seo.models.'.PlainPost::class => [
            'template' => ['title' => '%title% %sep% %sitename%'],
        ]]);

        $post = PlainPost::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);

        $this->assertSame('Bài viết mẫu - Trang Của Tôi', $post->seo()->title);
    }

    public function test_an_unresolved_token_takes_its_separator_with_it(): void
    {
        config(['seo.models.'.PlainPost::class => [
            'template' => ['title' => '%title% %sep% %category% %sep% %sitename%'],
        ]]);

        $post = PlainPost::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'x']);

        // Not "Bài viết mẫu -  - Trang Của Tôi".
        $this->assertSame('Bài viết mẫu - Trang Của Tôi', $post->seo()->title);
    }

    public function test_canonical_defaults_to_the_page_url(): void
    {
        $post = $this->makePost(['slug' => 'bai-viet-mau']);

        $this->assertSame('https://trangcuatoi.vn/bai-viet/bai-viet-mau', $post->seo()->canonical);
    }

    public function test_an_indexable_environment_carries_only_the_image_preview_default(): void
    {
        // Google's own recommendation for the most Discover/image traffic —
        // not a restriction, which is why this is not "no robots line at all".
        $this->assertSame('max-image-preview:large', $this->makePost()->seo()->robotsLine());
    }

    public function test_outside_an_indexable_environment_everything_is_noindex(): void
    {
        config(['seo.indexable_environments' => ['production']]);

        $post = $this->makePost();

        $this->assertSame('noindex, nofollow', $post->seo()->robotsLine());
    }

    public function test_a_stored_robots_rule_beats_the_environment_default(): void
    {
        config(['seo.indexable_environments' => ['production']]);

        $post = $this->makePost();
        $post->saveSeo(new SeoData(robots: [RobotsRule::noFollow()]));

        $this->assertSame('nofollow', $post->fresh()->seo()->robotsLine());
    }

    public function test_html_is_stripped_and_whitespace_collapsed(): void
    {
        $post = $this->makePost(['excerpt' => "<p>Mô  tả\n\ncó   thẻ</p>"]);

        $this->assertSame('Mô tả có thẻ', $post->seo()->description);
    }

    public function test_a_long_title_is_cut_at_a_word_boundary(): void
    {
        config(['seo.limits.title_pixels' => 200]);

        $post = $this->makePost(['name' => 'Hướng dẫn tối ưu SEO cho website Laravel từ đầu đến cuối']);

        $title = $post->seo()->title;

        $this->assertNotNull($title);
        $this->assertStringEndsWith('…', $title);
        $this->assertStringNotContainsString('  ', $title);
        $this->assertLessThan(56, mb_strlen($title));
    }

    public function test_a_removed_stage_stops_running(): void
    {
        config(['seo.limits.title_pixels' => 200]);
        config(['seo.pipeline' => array_values(array_filter(
            config('seo.pipeline'),
            static fn (string $stage): bool => $stage !== \Duxbo\Seo\Resolution\Stages\TruncateStage::class,
        ))]);

        $long = 'Hướng dẫn tối ưu SEO cho website Laravel từ đầu đến cuối';
        $post = $this->makePost(['name' => $long]);

        $this->assertSame($long, $post->seo()->title);
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
