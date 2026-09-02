<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\NotFound\NotFoundLogger;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class NotFoundTest extends TestCase
{
    use RefreshDatabase;

    protected function defineRoutes($router): void
    {
        $router->get('/ton-tai', static fn (): string => 'ok');
    }

    public function test_a_missing_page_is_recorded(): void
    {
        $this->get('/khong-co');

        $this->assertDatabaseHas('seo_not_found', ['path' => '/khong-co', 'hits' => 1]);
    }

    public function test_a_found_page_is_not_recorded(): void
    {
        $this->get('/ton-tai');

        $this->assertSame(0, DB::table('seo_not_found')->count());
    }

    public function test_repeat_hits_increment_a_counter_rather_than_adding_rows(): void
    {
        $this->get('/khong-co');
        $this->get('/khong-co');
        $this->get('/khong-co');

        // Bot scanning would otherwise add tens of thousands of rows a day.
        $this->assertSame(1, DB::table('seo_not_found')->count());
        $this->assertSame(3, (int) DB::table('seo_not_found')->value('hits'));
    }

    public function test_asset_and_probe_paths_are_excluded_by_default(): void
    {
        foreach (['/app.js', '/style.css', '/wp-admin/index.php', '/.env'] as $path) {
            $this->get($path);
        }

        $this->assertSame(0, DB::table('seo_not_found')->count());
    }

    public function test_the_table_is_capped(): void
    {
        config(['seo.not_found.max_rows' => 3]);

        foreach (range(1, 6) as $i) {
            $this->get("/khong-co-{$i}");
        }

        $this->assertLessThanOrEqual(3, DB::table('seo_not_found')->count());
    }

    public function test_sampling_can_be_turned_down(): void
    {
        config(['seo.not_found.sample_rate' => 0.0]);

        $this->get('/khong-co');

        $this->assertSame(0, DB::table('seo_not_found')->count());
    }

    public function test_pruning_removes_only_stale_entries(): void
    {
        $this->get('/moi');

        DB::table('seo_not_found')->insert([
            'path' => '/cu',
            'path_hash' => md5('/cu'),
            'hits' => 1,
            'first_seen_at' => Carbon::now()->subDays(200),
            'last_seen_at' => Carbon::now()->subDays(200),
        ]);

        $deleted = app(NotFoundLogger::class)->prune(90);

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('seo_not_found', ['path' => '/moi']);
        $this->assertDatabaseMissing('seo_not_found', ['path' => '/cu']);
    }

    public function test_the_monitor_can_be_switched_off(): void
    {
        config(['seo.not_found.enabled' => false]);

        $this->get('/khong-co');

        $this->assertSame(0, DB::table('seo_not_found')->count());
    }

    public function test_a_long_user_agent_is_truncated_rather_than_overflowing(): void
    {
        $this->withHeaders(['User-Agent' => str_repeat('a', 2000)])->get('/khong-co');

        $stored = (string) DB::table('seo_not_found')->value('user_agent');

        $this->assertSame(500, mb_strlen($stored));
    }
}
