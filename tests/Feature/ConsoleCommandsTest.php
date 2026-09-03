<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The two Artisan commands had shipped with zero test coverage — every other
 * public entry point in the package (routes, the facade, the API) is
 * exercised somewhere, and these were not. A typo in an option name or a
 * wrong constructor argument would only have surfaced when a real user ran
 * the command.
 */
final class ConsoleCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_reports_when_no_sources_are_registered(): void
    {
        $this->artisan('seo:sitemap')
            ->expectsOutputToContain('No sitemap sources are registered.')
            ->assertSuccessful();
    }

    public function test_sitemap_list_shows_sources_without_writing_files(): void
    {
        config(['seo.sitemap.sources' => [
            ['pages' => ['/', '/gioi-thieu'], 'name' => 'pages'],
        ]]);

        $this->artisan('seo:sitemap', ['--list' => true])
            ->expectsOutputToContain('pages')
            ->assertSuccessful();
    }

    public function test_sitemap_writes_files_to_the_given_path(): void
    {
        $this->makePost();

        config(['seo.sitemap.sources' => [
            ['model' => Post::class, 'name' => 'posts'],
        ]]);

        $directory = sys_get_temp_dir().'/seo-sitemap-cmd-'.uniqid();

        $this->artisan('seo:sitemap', ['--path' => $directory])
            ->expectsOutputToContain('file(s) written')
            ->assertSuccessful();

        $this->assertFileExists($directory.'/sitemap.xml');
        $this->assertFileExists($directory.'/sitemap-posts.xml');

        array_map('unlink', glob($directory.'/*.xml') ?: []);
        rmdir($directory);
    }

    public function test_prune_404_rejects_a_days_value_below_one(): void
    {
        $this->artisan('seo:prune-404', ['--days' => '0'])
            ->expectsOutputToContain('--days must be at least 1')
            ->assertFailed();
    }

    public function test_prune_404_deletes_only_stale_entries(): void
    {
        DB::table('seo_not_found')->insert([
            ['path' => '/moi', 'path_hash' => md5('/moi'), 'hits' => 1,
                'first_seen_at' => Carbon::now(), 'last_seen_at' => Carbon::now()],
            ['path' => '/cu', 'path_hash' => md5('/cu'), 'hits' => 1,
                'first_seen_at' => Carbon::now()->subDays(200), 'last_seen_at' => Carbon::now()->subDays(200)],
        ]);

        $this->artisan('seo:prune-404', ['--days' => '90'])
            ->expectsOutputToContain('Deleted 1 entr(ies)')
            ->assertSuccessful();

        $this->assertDatabaseHas('seo_not_found', ['path' => '/moi']);
        $this->assertDatabaseMissing('seo_not_found', ['path' => '/cu']);
    }

    private function makePost(): Post
    {
        return Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
    }
}
