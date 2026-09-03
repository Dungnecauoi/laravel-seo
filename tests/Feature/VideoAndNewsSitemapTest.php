<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Data\SitemapNews;
use Duxbo\Seo\Data\SitemapVideo;
use Duxbo\Seo\Sitemap\SitemapWriter;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\Fixtures\VideoPost;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

final class VideoAndNewsSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_video_entry_needs_a_content_or_player_location(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/contentLoc or playerLoc/');

        new SitemapVideo(thumbnailLoc: 'https://x/thumb.jpg', title: 'X', description: 'Y');
    }

    public function test_a_model_declaring_videos_gets_them_in_the_sitemap(): void
    {
        VideoPost::query()->create(['name' => 'Video mẫu', 'slug' => 'video-mau']);

        config(['seo.sitemap.cache_ttl' => 0, 'seo.sitemap.sources' => [
            ['model' => VideoPost::class, 'name' => 'videos'],
        ]]);

        $body = (string) $this->get('/sitemap-videos.xml')->getContent();

        $this->assertStringContainsString('xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"', $body);
        $this->assertStringContainsString('<video:thumbnail_loc>https://trangcuatoi.vn/thumb/video-mau.jpg</video:thumbnail_loc>', $body);
        $this->assertStringContainsString('<video:title>Video mẫu</video:title>', $body);
        $this->assertStringContainsString('<video:content_loc>', $body);
        $this->assertStringContainsString('<video:duration>120</video:duration>', $body);
    }

    public function test_a_model_without_videos_emits_no_video_block(): void
    {
        Post::query()->create(['name' => 'Bài thường', 'slug' => 'bai-thuong']);

        config(['seo.sitemap.cache_ttl' => 0, 'seo.sitemap.sources' => [
            ['model' => Post::class, 'name' => 'posts'],
        ]]);

        $body = (string) $this->get('/sitemap-posts.xml')->getContent();

        $this->assertStringNotContainsString('<video:video>', $body);
    }

    public function test_a_recent_article_appears_in_the_news_sitemap(): void
    {
        Post::query()->create(['name' => 'Tin nóng', 'slug' => 'tin-nong']);

        config(['seo.sitemap.cache_ttl' => 0, 'seo.sitemap.sources' => [
            ['model' => Post::class, 'name' => 'tin-tuc', 'news' => [
                'publication_name' => 'Báo Của Tôi',
                'publication_language' => 'vi',
                'date_column' => 'created_at',
            ]],
        ]]);

        $body = (string) $this->get('/sitemap-tin-tuc.xml')->getContent();

        $this->assertStringContainsString('xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"', $body);
        $this->assertStringContainsString('<news:name>Báo Của Tôi</news:name>', $body);
        $this->assertStringContainsString('<news:language>vi</news:language>', $body);
        $this->assertStringContainsString('<news:title>Tin nóng</news:title>', $body);
    }

    public function test_an_article_older_than_the_window_is_excluded(): void
    {
        $stale = Post::query()->create(['name' => 'Tin cũ', 'slug' => 'tin-cu']);
        $stale->forceFill(['created_at' => Carbon::now()->subDays(5)])->save();

        config(['seo.sitemap.cache_ttl' => 0, 'seo.sitemap.sources' => [
            ['model' => Post::class, 'name' => 'tin-tuc', 'news' => [
                'publication_name' => 'Báo Của Tôi',
                'publication_language' => 'vi',
                'date_column' => 'created_at',
            ]],
        ]]);

        $body = (string) $this->get('/sitemap-tin-tuc.xml')->getContent();

        // Google News rejects an article past 48 hours outright, so this is
        // not optional filtering — an old article simply has no business in
        // this sitemap at all.
        $this->assertStringNotContainsString('Tin cũ', $body);
    }

    public function test_the_window_is_configurable(): void
    {
        $post = Post::query()->create(['name' => 'Tin 3 ngày', 'slug' => 'tin-3-ngay']);
        $post->forceFill(['created_at' => Carbon::now()->subDays(3)])->save();

        config(['seo.sitemap.cache_ttl' => 0, 'seo.sitemap.sources' => [
            ['model' => Post::class, 'name' => 'tin-tuc', 'news' => [
                'publication_name' => 'Báo Của Tôi',
                'publication_language' => 'vi',
                'date_column' => 'created_at',
                'max_age_hours' => 24 * 7,
            ]],
        ]]);

        $body = (string) $this->get('/sitemap-tin-tuc.xml')->getContent();

        $this->assertStringContainsString('Tin 3 ngày', $body);
    }

    public function test_a_noindexed_article_is_excluded_from_the_news_sitemap(): void
    {
        $post = Post::query()->create(['name' => 'Tin ẩn', 'slug' => 'tin-an']);
        $post->saveSeo(['robots' => ['noindex']]);

        config(['seo.sitemap.cache_ttl' => 0, 'seo.sitemap.sources' => [
            ['model' => Post::class, 'name' => 'tin-tuc', 'news' => [
                'publication_name' => 'Báo Của Tôi',
                'publication_language' => 'vi',
                'date_column' => 'created_at',
            ]],
        ]]);

        $body = (string) $this->get('/sitemap-tin-tuc.xml')->getContent();

        $this->assertStringNotContainsString('Tin ẩn', $body);
    }

    public function test_a_writer_used_directly_renders_a_full_news_entry(): void
    {
        $xml = SitemapWriter::toMemory()
            ->startUrlSet(news: true)
            ->writeUrl(new \Duxbo\Seo\Data\SitemapUrl(
                loc: 'https://trangcuatoi.vn/tin-tuc/a',
                news: new SitemapNews(
                    publicationName: 'Báo Của Tôi',
                    publicationLanguage: 'vi',
                    publicationDate: Carbon::now(),
                    title: 'Tiêu đề tin',
                    genres: 'PressRelease',
                    keywords: 'seo, laravel',
                ),
            ))
            ->finish();

        $this->assertStringContainsString('<news:genres>PressRelease</news:genres>', $xml);
        $this->assertStringContainsString('<news:keywords>seo, laravel</news:keywords>', $xml);
    }
}
