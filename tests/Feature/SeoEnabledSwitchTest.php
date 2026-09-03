<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Data\RobotsRule;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\Sitemap\SitemapGenerator;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * `seo.enabled = false` — the safety net for a demo domain shown to a client
 * before launch. Unlike `indexable_environments`, this is not a default a
 * stored per-page value is allowed to beat: it means "not this domain",
 * full stop.
 */
final class SeoEnabledSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.enabled', false);
        $app['config']->set('seo.sitemap.cache_ttl', 0);
        $app['config']->set('seo.sitemap.sources', [
            ['model' => Post::class, 'name' => 'posts'],
        ]);
    }

    public function test_every_page_is_forced_noindex(): void
    {
        $this->assertSame('noindex, nofollow', $this->makePost()->seo()->robotsLine());
    }

    public function test_a_stored_index_value_cannot_defeat_the_switch(): void
    {
        $post = $this->makePost();
        $post->saveSeo(new SeoData(robots: [RobotsRule::make(\Duxbo\Seo\Enums\RobotsDirective::Index)]));

        // Different from the indexable_environments default, which a stored
        // value is allowed to beat — this one is not defeatable by anything
        // a content editor sets on an individual page.
        $this->assertSame('noindex, nofollow', $post->fresh()->seo()->robotsLine());
    }

    public function test_robots_txt_disallows_everything(): void
    {
        $body = (string) $this->get('/robots.txt')->getContent();

        $this->assertStringContainsString('Disallow: /', $body);
    }

    public function test_the_sitemap_publishes_nothing(): void
    {
        $this->assertSame([], app(SitemapGenerator::class)->sources());

        $this->get('/sitemap-posts.xml')->assertNotFound();
    }

    public function test_meta_tags_still_render_for_a_shared_preview(): void
    {
        // A demo link shared in Slack should still preview nicely — only
        // what governs indexing is affected, not the rest of the page.
        $post = $this->makePost(['name' => 'Bài viết mẫu']);

        $html = (string) $post->seoTags();

        $this->assertStringContainsString('<title>Bài viết mẫu</title>', $html);
        $this->assertStringContainsString('noindex', $html);
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
