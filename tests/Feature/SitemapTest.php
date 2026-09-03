<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Sitemap\SitemapGenerator;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class SitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.sitemap.cache_ttl', 0);
        $app['config']->set('seo.sitemap.sources', [
            ['model' => Post::class, 'name' => 'posts', 'changefreq' => 'weekly', 'priority' => 0.8],
            ['pages' => ['/', '/gioi-thieu'], 'name' => 'pages'],
        ]);
    }

    public function test_a_record_marked_noindex_is_excluded_from_the_sitemap(): void
    {
        $this->makePost(['slug' => 'hien-thi']);
        $hidden = $this->makePost(['slug' => 'an-di']);
        $hidden->saveSeo(['robots' => ['noindex']]);

        // Telling a crawler "please index this" here and "don't" in the
        // page's own robots meta is exactly the contradiction Search Console
        // flags as "Submitted URL marked noindex".
        $body = (string) $this->get('/sitemap-posts.xml')->getContent();

        $this->assertStringContainsString('hien-thi', $body);
        $this->assertStringNotContainsString('an-di', $body);
    }

    public function test_the_index_lists_one_entry_per_source(): void
    {
        $this->makePost();

        $xml = $this->get('/sitemap.xml');

        $xml->assertOk();
        $xml->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml->assertSee('sitemap-posts.xml', escape: false);
        $xml->assertSee('sitemap-pages.xml', escape: false);
    }

    public function test_a_source_file_lists_its_urls(): void
    {
        $this->makePost(['slug' => 'bai-mot']);
        $this->makePost(['slug' => 'bai-hai']);

        $response = $this->get('/sitemap-posts.xml');

        $response->assertOk();
        $response->assertSee('https://trangcuatoi.vn/bai-viet/bai-mot', escape: false);
        $response->assertSee('https://trangcuatoi.vn/bai-viet/bai-hai', escape: false);
        $response->assertSee('<changefreq>weekly</changefreq>', escape: false);
        $response->assertSee('<priority>0.8</priority>', escape: false);
    }

    public function test_an_unknown_source_is_a_404_not_an_empty_sitemap(): void
    {
        // An empty file claims the section has no pages, which is a different
        // and worse thing to tell a crawler.
        $this->get('/sitemap-khong-ton-tai.xml')->assertNotFound();
    }

    public function test_ampersands_in_urls_are_escaped(): void
    {
        config(['seo.sitemap.sources' => [
            ['pages' => ['/tim?a=1&b=2'], 'name' => 'pages'],
        ]]);

        $body = $this->get('/sitemap-pages.xml')->getContent();

        // Unescaped & is the most common way a sitemap ends up invalid.
        $this->assertStringContainsString('&amp;b=2', (string) $body);
        $this->assertStringNotContainsString('a=1&b=2', (string) $body);
    }

    public function test_a_source_over_the_limit_is_split_and_both_parts_are_listed(): void
    {
        config(['seo.sitemap.max_urls' => 2]);

        foreach (range(1, 5) as $i) {
            $this->makePost(['slug' => "bai-{$i}"]);
        }

        $index = (string) $this->get('/sitemap.xml')->getContent();

        // Five URLs at two per file is three parts.
        $this->assertStringContainsString('sitemap-posts.xml', $index);
        $this->assertStringContainsString('sitemap-posts-2.xml', $index);
        $this->assertStringContainsString('sitemap-posts-3.xml', $index);

        $part2 = (string) $this->get('/sitemap-posts-2.xml')->getContent();

        $this->assertSame(2, substr_count($part2, '<loc>'));
        $this->assertStringContainsString('bai-3', $part2);
    }

    public function test_the_url_limit_is_capped_at_the_protocol_maximum(): void
    {
        config(['seo.sitemap.max_urls' => 999999]);

        $this->assertSame(50000, app(SitemapGenerator::class)->maxUrls());
    }

    public function test_hreflang_alternates_appear_only_on_a_multilingual_site(): void
    {
        $post = $this->makePost();

        // The sitemap has no "currently rendering locale" the way a request
        // does — unlike a formatter, nothing here is free. At least two
        // locales need their own evidence (a stored row, absent
        // HasAlternateLocales) before an alternate link is worth emitting;
        // one alone has nothing to pair against.
        $post->saveSeo(['title' => 'Tiếng Việt'], 'vi');
        $post->saveSeo(['title' => 'English'], 'en');

        $this->assertStringNotContainsString('xhtml:link', (string) $this->get('/sitemap-posts.xml')->getContent());

        config(['seo.locales.supported' => ['vi', 'en']]);

        $body = (string) $this->get('/sitemap-posts.xml')->getContent();

        $this->assertStringContainsString('hreflang="en"', $body);
        $this->assertStringContainsString('hreflang="vi"', $body);
    }

    public function test_a_record_with_no_translation_evidence_gets_no_alternates(): void
    {
        // Nothing stored for any locale, and the model does not implement
        // HasAlternateLocales — the old behaviour assumed every record
        // existed in every globally supported locale, which is exactly what
        // put a link to a 404 in a sitemap for a page with one translation.
        $this->makePost();

        config(['seo.locales.supported' => ['vi', 'en']]);

        $body = (string) $this->get('/sitemap-posts.xml')->getContent();

        $this->assertStringNotContainsString('xhtml:link', $body);
    }

    public function test_a_listener_can_exclude_a_url(): void
    {
        $this->makePost(['slug' => 'giu-lai']);
        $this->makePost(['slug' => 'bo-di']);

        \Illuminate\Support\Facades\Event::listen(
            \Duxbo\Seo\Events\SitemapUrlAdded::class,
            static function ($event): void {
                if (str_contains($event->url->loc, 'bo-di')) {
                    $event->exclude();
                }
            },
        );

        $body = (string) $this->get('/sitemap-posts.xml')->getContent();

        $this->assertStringContainsString('giu-lai', $body);
        $this->assertStringNotContainsString('bo-di', $body);
    }

    public function test_writing_to_disk_produces_the_index_and_every_part(): void
    {
        $this->makePost();

        $directory = sys_get_temp_dir().'/seo-sitemap-'.uniqid();

        $written = app(SitemapGenerator::class)->writeTo($directory);

        $this->assertContains($directory.'/sitemap.xml', $written);
        $this->assertContains($directory.'/sitemap-posts.xml', $written);
        $this->assertFileExists($directory.'/sitemap-posts.xml');

        array_map('unlink', $written);
        rmdir($directory);
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
