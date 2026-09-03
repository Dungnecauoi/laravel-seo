<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The JSON twin of PanelShellTest — the same dashboard, content list,
 * redirect and 404-monitor actions, and settings view, reached through
 * /api/seo/v1 instead of the Blade panel's session routes. React and Vue
 * build their own admin surfaces on this rather than the Blade one.
 */
final class ApiShellTest extends TestCase
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

    public function test_the_dashboard_reports_stats(): void
    {
        $this->makePost();

        $this->getJson('/api/seo/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('totalRecords', 1)
            ->assertJsonPath('exposedTypes', ['post']);
    }

    public function test_the_content_list_returns_resolved_titles(): void
    {
        $this->makePost(['name' => 'Bài viết mẫu']);

        $this->getJson('/api/seo/v1/content?type=post')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Bài viết mẫu')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_settings_reports_the_master_switch_state(): void
    {
        $this->getJson('/api/seo/v1/settings')
            ->assertOk()
            ->assertJsonPath('seoEnabled', true)
            ->assertJsonPath('exposedModels', ['post']);

        config(['seo.enabled' => false]);

        $this->getJson('/api/seo/v1/settings')->assertJsonPath('seoEnabled', false);
    }

    public function test_a_redirect_can_be_listed_created_toggled_and_deleted(): void
    {
        $this->postJson('/api/seo/v1/redirects', [
            'source' => '/cu',
            'target' => '/moi',
            'type' => 'exact',
            'status' => '301',
        ])->assertCreated();

        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/cu', 'target' => '/moi']);

        $this->getJson('/api/seo/v1/redirects')
            ->assertOk()
            ->assertJsonPath('data.0.source', '/cu');

        $id = DB::table('seo_redirects')->value('id');

        $this->patchJson("/api/seo/v1/redirects/{$id}/toggle")
            ->assertOk()
            ->assertJsonPath('isActive', false);

        $this->assertDatabaseHas('seo_redirects', ['id' => $id, 'is_active' => false]);

        $this->deleteJson("/api/seo/v1/redirects/{$id}")->assertOk();
        $this->assertDatabaseMissing('seo_redirects', ['id' => $id]);
    }

    public function test_an_unsafe_redirect_target_is_a_422_not_a_500(): void
    {
        $this->postJson('/api/seo/v1/redirects', [
            'source' => '/khuyen-mai',
            'target' => 'https://trang-lua-dao.com',
            'type' => 'exact',
            'status' => '301',
        ])->assertStatus(422)->assertJsonValidationErrors('source');

        $this->assertDatabaseMissing('seo_redirects', ['source_path' => '/khuyen-mai']);
    }

    public function test_pruning_old_404_entries(): void
    {
        DB::table('seo_not_found')->insert([
            'path' => '/cu', 'path_hash' => md5('/cu'), 'hits' => 1,
            'first_seen_at' => now()->subDays(200), 'last_seen_at' => now()->subDays(200),
        ]);

        $this->postJson('/api/seo/v1/not-found/prune', ['days' => 90])
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseMissing('seo_not_found', ['path' => '/cu']);
    }

    public function test_turning_a_404_into_a_redirect_removes_it_from_the_log(): void
    {
        DB::table('seo_not_found')->insert([
            'id' => 1, 'path' => '/duong-dan-cu', 'path_hash' => md5('/duong-dan-cu'),
            'hits' => 5, 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $this->postJson('/api/seo/v1/not-found/1/redirect', ['target' => '/duong-dan-moi'])
            ->assertCreated();

        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/duong-dan-cu', 'target' => '/duong-dan-moi']);
        $this->assertDatabaseMissing('seo_not_found', ['id' => 1]);
    }

    public function test_every_new_route_is_denied_by_default_without_the_gate(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => false);

        $this->getJson('/api/seo/v1/dashboard')->assertForbidden();
        $this->getJson('/api/seo/v1/content')->assertForbidden();
        $this->getJson('/api/seo/v1/settings')->assertForbidden();
        $this->getJson('/api/seo/v1/redirects')->assertForbidden();
        $this->postJson('/api/seo/v1/not-found/prune')->assertForbidden();
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-mau',
        ]);
    }
}
