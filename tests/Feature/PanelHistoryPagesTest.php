<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class PanelHistoryPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.panel.enabled', true);
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

    public function test_every_new_page_renders_when_empty(): void
    {
        $this->get('/seo/panel/audit-history')->assertOk()->assertSee('Chưa có lần audit');
        $this->get('/seo/panel/internal-links')->assertOk();
        $this->get('/seo/panel/search-console')->assertOk()->assertSee('Chưa có dữ liệu');
        $this->get('/seo/panel/indexnow-log')->assertOk()->assertSee('Chưa có lần gửi');
    }

    public function test_audit_history_shows_scored_batches(): void
    {
        AuditBatch::query()->create([
            'model' => Post::class,
            'total_records' => 5,
            'average_score' => 72.5,
            'min_score' => 40,
            'max_score' => 90,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->get('/seo/panel/audit-history')
            ->assertOk()
            ->assertSee('72.5')
            ->assertSee('Post');
    }

    public function test_internal_links_flags_an_orphan_page(): void
    {
        Post::query()->create(['name' => 'Mồ côi', 'slug' => 'mo-coi']);

        $this->get('/seo/panel/internal-links?type=post')
            ->assertOk()
            ->assertSee('Mồ côi — không ai link tới');
    }

    public function test_search_console_shows_synced_rows(): void
    {
        DB::table('seo_search_console_stats')->insert([
            'url' => 'https://trangcuatoi.vn/a',
            'url_hash' => md5('a'),
            'date' => now(),
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'position' => 5.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('/seo/panel/search-console')
            ->assertOk()
            ->assertSee('https://trangcuatoi.vn/a')
            ->assertSee('10');
    }

    public function test_indexnow_log_escapes_the_error_message(): void
    {
        DB::table('seo_indexnow_log')->insert([
            'urls' => json_encode(['/x']),
            'url_count' => 1,
            'successful' => false,
            'status_code' => 403,
            'error' => '<script>alert(1)</script>',
            'created_at' => now(),
        ]);

        $body = (string) $this->get('/seo/panel/indexnow-log')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function test_fixed_segment_routes_are_not_swallowed_by_the_type_id_catch_all(): void
    {
        $this->get('/seo/panel/audit-history')->assertOk();
        $this->get('/seo/panel/internal-links')->assertOk();
        $this->get('/seo/panel/search-console')->assertOk();
        $this->get('/seo/panel/indexnow-log')->assertOk();
    }
}
