<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\BrokenLinks\PublicUrlGuard;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class BrokenLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The command's own SSRF guard does a real DNS lookup for any host
        // that isn't a literal IP — a fake domain like vidu-ngoai.com either
        // fails to resolve or resolves unpredictably, so every test in this
        // file that isn't specifically about the guard replaces it with one
        // that treats any host as resolving to a known-public address.
        // PublicUrlGuardTest covers the guard's real blocking logic.
        $this->app->instance(
            PublicUrlGuard::class,
            new PublicUrlGuard(static fn (string $host): array => ['1.1.1.1']),
        );
    }

    public function test_rejects_an_unknown_model_class(): void
    {
        $this->artisan('seo:broken-links', ['model' => 'Not\A\Real\Class'])
            ->expectsOutputToContain('No model class')
            ->assertFailed();
    }

    public function test_extracts_external_links_from_content_and_stores_them(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $post = $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/x">Xem thêm</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('found 1 external link(s), 1 unique URL')
            ->assertSuccessful();

        $row = DB::table('seo_external_links')->first();

        $this->assertSame((string) $post->getKey(), $row->source_id);
        $this->assertSame('https://vidu-ngoai.com/x', $row->target_url);
        $this->assertSame('Xem thêm', $row->anchor_text);
    }

    public function test_internal_links_are_not_stored(): void
    {
        Http::fake();

        $this->makePost(['excerpt' => '<a href="/bai-viet/khac">Nội bộ</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('found 0 external link(s)')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('seo_external_links')->count());
        Http::assertNothingSent();
    }

    public function test_a_working_link_is_checked_and_reported_as_not_broken(): void
    {
        Http::fake(['vidu-ngoai.com/*' => Http::response('', 200)]);

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/con-song">a</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('No broken links found')
            ->assertSuccessful();

        $row = DB::table('seo_link_checks')->first();
        $this->assertTrue((bool) $row->successful);
        $this->assertSame(200, $row->status_code);
    }

    public function test_a_dead_link_is_checked_and_reported_as_broken(): void
    {
        Http::fake(['vidu-ngoai.com/*' => Http::response('Not Found', 404)]);

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/da-mat">a</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('1 broken link(s) found')
            ->assertSuccessful();

        $row = DB::table('seo_link_checks')->first();
        $this->assertFalse((bool) $row->successful);
        $this->assertSame(404, $row->status_code);
    }

    public function test_a_server_refusing_head_falls_back_to_get(): void
    {
        Http::fake([
            'vidu-ngoai.com/*' => Http::sequence()
                ->push('', 405)
                ->push('', 200),
        ]);

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/chi-nhan-get">a</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('No broken links found')
            ->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_a_redirect_to_a_still_public_page_is_followed(): void
    {
        Http::fake([
            'vidu-ngoai.com/cu' => Http::response('', 301, ['Location' => 'https://vidu-ngoai.com/moi']),
            'vidu-ngoai.com/moi' => Http::response('', 200),
        ]);

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/cu">a</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('No broken links found')
            ->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_a_url_the_ssrf_guard_blocks_is_never_requested(): void
    {
        // The real guard, not the permissive stub setUp() installs — this is
        // the one test in the file that exercises the actual SSRF defense.
        $this->app->instance(PublicUrlGuard::class, new PublicUrlGuard());

        Http::fake();

        $this->makePost(['excerpt' => '<a href="http://169.254.169.254/latest/meta-data/">metadata</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('1 broken link(s) found')
            ->assertSuccessful();

        Http::assertNothingSent();

        $row = DB::table('seo_link_checks')->first();
        $this->assertFalse((bool) $row->successful);
        $this->assertNull($row->status_code);
        $this->assertStringContainsString('non-public address', $row->error);
    }

    public function test_a_redirect_to_a_blocked_address_is_not_followed(): void
    {
        Http::fake([
            'vidu-ngoai.com/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/']),
        ]);

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/chuyen-huong">a</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('1 broken link(s) found')
            ->assertSuccessful();

        // The first hop (a plain public host) is allowed and requested, but
        // the Location it answers with is not — that second hop must never
        // be dialed.
        Http::assertSentCount(1);
        Http::assertNotSent(static fn ($request): bool => str_contains((string) $request->url(), '169.254.169.254'));
    }

    public function test_the_same_url_cited_by_multiple_records_is_checked_once(): void
    {
        Http::fake(['vidu-ngoai.com/*' => Http::response('', 200)]);

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/chung">a</a>']);
        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/chung">b</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])
            ->expectsOutputToContain('found 2 external link(s), 1 unique URL')
            ->assertSuccessful();

        $this->assertSame(2, DB::table('seo_external_links')->count());
        $this->assertSame(1, DB::table('seo_link_checks')->count());
        Http::assertSentCount(1);
    }

    public function test_check_can_be_skipped(): void
    {
        Http::fake();

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/x">a</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt', '--check' => '0'])
            ->assertSuccessful();

        $this->assertSame(0, DB::table('seo_link_checks')->count());
        Http::assertNothingSent();
    }

    public function test_re_crawling_replaces_old_links_rather_than_accumulating_them(): void
    {
        Http::fake(['vidu-ngoai.com/*' => Http::response('', 200)]);

        $post = $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/x">x</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])->assertSuccessful();
        $this->assertSame(1, DB::table('seo_external_links')->count());

        $post->update(['excerpt' => null]);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])->assertSuccessful();
        $this->assertSame(0, DB::table('seo_external_links')->count());
    }

    public function test_re_checking_the_same_url_updates_rather_than_duplicates(): void
    {
        Http::fake([
            'vidu-ngoai.com/*' => Http::sequence()
                ->push('', 200)
                ->push('Not Found', 404),
        ]);

        $this->makePost(['excerpt' => '<a href="https://vidu-ngoai.com/x">x</a>']);

        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])->assertSuccessful();
        $this->artisan('seo:broken-links', ['model' => Post::class, '--content' => 'excerpt'])->assertSuccessful();

        $this->assertSame(1, DB::table('seo_link_checks')->count());
        $this->assertSame(404, DB::table('seo_link_checks')->value('status_code'));
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
