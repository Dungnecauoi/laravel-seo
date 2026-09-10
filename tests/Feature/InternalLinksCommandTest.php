<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

final class InternalLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_an_unknown_model_class(): void
    {
        $this->artisan('seo:internal-links', ['model' => 'Not\A\Real\Class'])
            ->expectsOutputToContain('No model class')
            ->assertFailed();
    }

    public function test_extracts_internal_links_from_content_and_stores_them(): void
    {
        $this->makePost(['slug' => 'bai-a']);
        $b = $this->makePost([
            'slug' => 'bai-b',
            'excerpt' => '<a href="/bai-viet/bai-a">Xem thêm</a>',
        ]);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('found 1 internal link')
            ->assertSuccessful();

        $row = DB::table('seo_internal_links')->first();

        $this->assertSame((string) $b->getKey(), $row->source_id);
        // Made absolute against app.url — Post::seoUrl() itself points at a
        // hard-coded trangcuatoi.vn regardless of what app.url is, which is
        // exactly why matching happens on path (below), not on this value.
        $this->assertSame('http://localhost/bai-viet/bai-a', $row->target_url);
        $this->assertSame('Xem thêm', $row->anchor_text);
    }

    public function test_flags_a_page_that_nothing_in_the_set_links_to(): void
    {
        $this->makePost(['slug' => 'mo-coi']);
        $this->makePost([
            'slug' => 'bai-b',
            'excerpt' => '<a href="/bai-viet/bai-b">tự trỏ vào chính nó</a>',
        ]);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('1 orphan page(s)')
            ->expectsOutputToContain('mo-coi')
            ->assertSuccessful();
    }

    public function test_no_orphans_when_every_page_links_to_another(): void
    {
        $this->makePost(['slug' => 'bai-a', 'excerpt' => '<a href="/bai-viet/bai-b">b</a>']);
        $this->makePost(['slug' => 'bai-b', 'excerpt' => '<a href="/bai-viet/bai-a">a</a>']);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('No orphans')
            ->assertSuccessful();
    }

    public function test_matching_ignores_scheme_and_host_and_compares_by_path(): void
    {
        // Post::seoUrl() is hard-coded to https://trangcuatoi.vn/… regardless
        // of app.url, which defaults to localhost in this suite — an href
        // made absolute against app.url must still be recognised as pointing
        // at the same page by its path.
        $this->makePost(['slug' => 'bai-a']);
        $this->makePost(['slug' => 'bai-b', 'excerpt' => '<a href="/bai-viet/bai-a">a</a>']);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('1 orphan page(s)')
            // bai-a is linked to and must not be listed; only bai-b (nothing
            // points back to it) should be.
            ->doesntExpectOutputToContain('bai-viet/bai-a')
            ->assertSuccessful();
    }

    public function test_re_crawling_replaces_old_links_rather_than_accumulating_them(): void
    {
        $post = $this->makePost(['excerpt' => '<a href="/bai-viet/x">x</a>']);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])->assertSuccessful();
        $this->assertSame(1, DB::table('seo_internal_links')->count());

        $post->update(['excerpt' => null]);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])->assertSuccessful();
        $this->assertSame(0, DB::table('seo_internal_links')->count());
    }

    public function test_crawling_one_locale_does_not_delete_another_locales_rows(): void
    {
        $post = $this->makePost(['excerpt' => '<a href="/bai-viet/vi-target">vi</a>']);

        $this->artisan('seo:internal-links', [
            'model' => Post::class, '--content' => 'excerpt', '--locale' => 'vi',
        ])->assertSuccessful();

        $post->update(['excerpt' => '<a href="/bai-viet/en-target">en</a>']);

        $this->artisan('seo:internal-links', [
            'model' => Post::class, '--content' => 'excerpt', '--locale' => 'en',
        ])->assertSuccessful();

        // Before the fix, the second (en) crawl's delete-by-source-id alone
        // would have wiped the first (vi) crawl's rows too.
        $this->assertSame(1, DB::table('seo_internal_links')->where('locale', 'vi')->count());
        $this->assertSame(1, DB::table('seo_internal_links')->where('locale', 'en')->count());
        $this->assertDatabaseHas('seo_internal_links', ['locale' => 'vi', 'target_url' => 'http://localhost/bai-viet/vi-target']);
        $this->assertDatabaseHas('seo_internal_links', ['locale' => 'en', 'target_url' => 'http://localhost/bai-viet/en-target']);
    }

    public function test_recrawling_the_same_locale_still_replaces_only_its_own_rows(): void
    {
        $post = $this->makePost(['excerpt' => '<a href="/bai-viet/vi-old">old</a>']);

        $this->artisan('seo:internal-links', [
            'model' => Post::class, '--content' => 'excerpt', '--locale' => 'vi',
        ])->assertSuccessful();

        $post->update(['excerpt' => '<a href="/bai-viet/vi-new">new</a>']);

        $this->artisan('seo:internal-links', [
            'model' => Post::class, '--content' => 'excerpt', '--locale' => 'vi',
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('seo_internal_links')->where('locale', 'vi')->count());
        $this->assertDatabaseHas('seo_internal_links', ['locale' => 'vi', 'target_url' => 'http://localhost/bai-viet/vi-new']);
        $this->assertDatabaseMissing('seo_internal_links', ['target_url' => 'http://localhost/bai-viet/vi-old']);
    }

    public function test_no_locale_option_stores_a_null_locale_as_before(): void
    {
        $this->makePost(['excerpt' => '<a href="/bai-viet/x">x</a>']);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->assertSuccessful();

        $this->assertNull(DB::table('seo_internal_links')->value('locale'));
    }

    public function test_external_links_are_not_stored(): void
    {
        $this->makePost(['excerpt' => '<a href="https://google.com">Google</a>']);

        $this->artisan('seo:internal-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('found 0 internal link')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('seo_internal_links')->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-'.uniqid(),
        ]);
    }
}
