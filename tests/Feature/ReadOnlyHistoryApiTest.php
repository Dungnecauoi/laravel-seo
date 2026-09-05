<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Audit\Audit;
use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The four read-only endpoints exposing what a console command already
 * wrote — seo:audit, seo:internal-links, seo:search-console:sync, and
 * every IndexNow submission's own log.
 */
final class ReadOnlyHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.api.enabled', true);
        $app['config']->set('seo.api.models', ['post']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Relation::enforceMorphMap(['post' => Post::class]);
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function test_every_new_route_is_denied_without_the_gate(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => false);

        $this->getJson('/api/seo/v1/audit-history')->assertForbidden();
        $this->getJson('/api/seo/v1/internal-links')->assertForbidden();
        $this->getJson('/api/seo/v1/search-console/stats')->assertForbidden();
        $this->getJson('/api/seo/v1/indexnow/log')->assertForbidden();
    }

    public function test_audit_history_lists_batches_newest_first(): void
    {
        $older = AuditBatch::query()->create(['model' => Post::class, 'total_records' => 2, 'average_score' => 60, 'started_at' => now()->subDay(), 'finished_at' => now()->subDay()]);
        $newer = AuditBatch::query()->create(['model' => Post::class, 'total_records' => 3, 'average_score' => 80, 'started_at' => now(), 'finished_at' => now()]);

        $data = $this->getJson('/api/seo/v1/audit-history')->assertOk()->json('data');

        $this->assertSame($newer->id, $data[0]['id']);
        $this->assertSame($older->id, $data[1]['id']);
        // JSON has no separate integer/float syntax for a whole number, so a
        // round trip through json() decodes 80.0 back as the int 80.
        $this->assertEquals(80.0, $data[0]['averageScore']);
    }

    public function test_audit_history_filters_by_model(): void
    {
        AuditBatch::query()->create(['model' => 'App\\Other', 'total_records' => 1, 'started_at' => now()]);
        AuditBatch::query()->create(['model' => Post::class, 'total_records' => 1, 'started_at' => now()]);

        $data = $this->getJson('/api/seo/v1/audit-history?model='.urlencode(Post::class))->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame(Post::class, $data[0]['model']);
    }

    public function test_internal_links_flags_a_record_nothing_links_to_as_an_orphan(): void
    {
        $linked = Post::query()->create(['name' => 'A', 'slug' => 'bai-a']);
        $orphan = Post::query()->create(['name' => 'B', 'slug' => 'bai-b']);

        DB::table('seo_internal_links')->insert([
            'source_type' => 'post',
            'source_id' => (string) $orphan->getKey(),
            'target_url' => 'https://trangcuatoi.vn/bai-viet/bai-a',
            'target_hash' => md5('/bai-viet/bai-a'),
            'created_at' => now(),
        ]);

        $data = $this->getJson('/api/seo/v1/internal-links?type=post')->assertOk()->json('data');

        $rows = collect($data)->keyBy('id');

        $this->assertFalse($rows[$linked->getKey()]['isOrphan']);
        $this->assertSame(1, $rows[$linked->getKey()]['incomingLinks']);
        $this->assertTrue($rows[$orphan->getKey()]['isOrphan']);
        $this->assertSame(1, $rows[$orphan->getKey()]['outgoingLinks']);
    }

    public function test_search_console_stats_sums_clicks_per_url_and_excludes_old_rows(): void
    {
        DB::table('seo_search_console_stats')->insert([
            ['url' => 'https://trangcuatoi.vn/a', 'url_hash' => md5('a'), 'date' => now()->subDays(2), 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 4.0, 'created_at' => now(), 'updated_at' => now()],
            ['url' => 'https://trangcuatoi.vn/a', 'url_hash' => md5('a2'), 'date' => now()->subDays(1), 'clicks' => 7, 'impressions' => 70, 'ctr' => 0.1, 'position' => 3.0, 'created_at' => now(), 'updated_at' => now()],
            ['url' => 'https://trangcuatoi.vn/old', 'url_hash' => md5('old'), 'date' => now()->subDays(100), 'clicks' => 999, 'impressions' => 999, 'ctr' => 1, 'position' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $body = $this->getJson('/api/seo/v1/search-console/stats?days=30')->assertOk()->json();

        $this->assertSame(12, $body['totalClicks']);
        $this->assertSame('https://trangcuatoi.vn/a', $body['data'][0]['url']);
        $this->assertSame(12, $body['data'][0]['clicks']);
        $this->assertCount(1, $body['data']);
    }

    public function test_indexnow_log_lists_recent_submissions_newest_first(): void
    {
        DB::table('seo_indexnow_log')->insert([
            ['urls' => json_encode(['/a']), 'url_count' => 1, 'successful' => true, 'status_code' => 200, 'error' => null, 'created_at' => now()->subMinute()],
            ['urls' => json_encode(['/b', '/c']), 'url_count' => 2, 'successful' => false, 'status_code' => 403, 'error' => 'Forbidden', 'created_at' => now()],
        ]);

        $data = $this->getJson('/api/seo/v1/indexnow/log')->assertOk()->json('data');

        $this->assertSame(['/b', '/c'], $data[0]['urls']);
        $this->assertFalse($data[0]['successful']);
        $this->assertSame('Forbidden', $data[0]['error']);
        $this->assertTrue($data[1]['successful']);
    }
}
