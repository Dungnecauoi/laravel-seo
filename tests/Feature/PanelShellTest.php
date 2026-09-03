<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Redirects\RedirectRepository;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The shell added around the single-record editor: a dashboard, a content
 * list, redirect and 404-monitor CRUD, and a read-only settings page — all
 * under the same session/CSRF panel routes, sharing one Gate.
 *
 * Fixed-segment routes (redirects, not-found, content, settings) sit
 * alongside the {type}/{id} catch-all the single-record editor already used.
 * On paper a 2-segment path like GET not-found/prune could theoretically be
 * swallowed by {type}/{id} — these tests hit every new route for real rather
 * than trusting that reasoning.
 */
final class PanelShellTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.panel.enabled', true);
        $app['config']->set('seo.api.models', ['post']);
        $app['config']->set('app.url', 'http://localhost');
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

    public function test_the_dashboard_renders_with_stats(): void
    {
        $this->makePost();

        $this->get('/seo/panel')
            ->assertOk()
            ->assertSee('Tổng quan')
            ->assertSee('Bản ghi có SEO');
    }

    public function test_the_dashboard_is_denied_without_the_gate(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => false);

        $this->get('/seo/panel')->assertForbidden();
    }

    public function test_the_content_list_shows_resolved_titles(): void
    {
        $this->makePost(['name' => 'Bài viết mẫu']);

        $this->get('/seo/panel/content?type=post')
            ->assertOk()
            ->assertSee('Bài viết mẫu');
    }

    public function test_the_content_list_flags_a_record_with_no_title_at_all(): void
    {
        // seo.defaults.title is '%sitename%', so an untitled record still
        // resolves to something — this only happens when even that is empty.
        config(['seo.defaults.title' => null]);

        Post::query()->create(['name' => '', 'slug' => 'trong']);

        $this->get('/seo/panel/content?type=post')
            ->assertOk()
            ->assertSee('Chưa có tiêu đề');
    }

    public function test_settings_reports_the_master_switch_state(): void
    {
        $this->get('/seo/panel/settings')
            ->assertOk()
            ->assertSee('post');

        config(['seo.enabled' => false]);

        $this->get('/seo/panel/settings')
            ->assertOk()
            ->assertSee('Tắt — noindex toàn site');
    }

    public function test_redirects_index_renders(): void
    {
        $this->get('/seo/panel/redirects')->assertOk()->assertSee('Chuyển hướng');
    }

    public function test_a_redirect_can_be_created_through_the_panel(): void
    {
        $this->from('/seo/panel/redirects')
            ->post('/seo/panel/redirects', [
                'source' => '/cu',
                'target' => '/moi',
                'type' => 'exact',
                'status' => '301',
            ])
            ->assertRedirect('/seo/panel/redirects');

        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/cu', 'target' => '/moi']);
    }

    public function test_an_unsafe_redirect_target_is_a_validation_error_not_a_500(): void
    {
        $this->from('/seo/panel/redirects')
            ->post('/seo/panel/redirects', [
                'source' => '/khuyen-mai',
                'target' => 'https://trang-lua-dao.com',
                'type' => 'exact',
                'status' => '301',
            ])
            ->assertSessionHasErrors('source');

        $this->assertDatabaseMissing('seo_redirects', ['source_path' => '/khuyen-mai']);
    }

    public function test_a_redirect_can_be_toggled_and_deleted(): void
    {
        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');

        $this->post("/seo/panel/redirects/{$redirect->id}/toggle", ['_method' => 'PATCH']);
        $this->assertDatabaseHas('seo_redirects', ['id' => $redirect->id, 'is_active' => false]);

        $this->post("/seo/panel/redirects/{$redirect->id}", ['_method' => 'DELETE']);
        $this->assertDatabaseMissing('seo_redirects', ['id' => $redirect->id]);
    }

    public function test_not_found_index_renders(): void
    {
        $this->get('/seo/panel/not-found')->assertOk()->assertSee('404 Monitor');
    }

    public function test_not_found_entries_are_escaped_in_the_panel(): void
    {
        DB::table('seo_not_found')->insert([
            'path' => '/<script>alert(1)</script>',
            'path_hash' => md5('x'),
            'hits' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $body = (string) $this->get('/seo/panel/not-found')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function test_pruning_old_404_entries_through_the_panel(): void
    {
        DB::table('seo_not_found')->insert([
            'path' => '/cu', 'path_hash' => md5('/cu'), 'hits' => 1,
            'first_seen_at' => now()->subDays(200), 'last_seen_at' => now()->subDays(200),
        ]);

        $this->post('/seo/panel/not-found/prune', ['days' => 90])
            ->assertRedirect();

        $this->assertDatabaseMissing('seo_not_found', ['path' => '/cu']);
    }

    public function test_turning_a_404_into_a_redirect_removes_it_from_the_log(): void
    {
        DB::table('seo_not_found')->insert([
            'id' => 1, 'path' => '/duong-dan-cu', 'path_hash' => md5('/duong-dan-cu'),
            'hits' => 5, 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $this->post('/seo/panel/not-found/1/redirect', ['target' => '/duong-dan-moi'])
            ->assertRedirect('/seo/panel/redirects');

        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/duong-dan-cu', 'target' => '/duong-dan-moi']);
        $this->assertDatabaseMissing('seo_not_found', ['id' => 1]);
    }

    public function test_the_404_nav_badge_reflects_the_current_count(): void
    {
        DB::table('seo_not_found')->insert([
            'path' => '/x', 'path_hash' => md5('/x'), 'hits' => 1,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        // Rendered from any panel page, not only the 404 monitor's own.
        $this->get('/seo/panel/redirects')->assertSee('1', false);
    }

    public function test_fixed_segment_routes_are_not_swallowed_by_the_type_id_catch_all(): void
    {
        // On paper, {type}/{id} (GET, 2 segments) could shadow a 2-segment
        // path like "not-found/prune" if the wrong HTTP verb were used, or if
        // registration order were different. Confirms it is not, for real,
        // across every fixed route added.
        $this->get('/seo/panel/content')->assertOk();
        $this->get('/seo/panel/redirects')->assertOk();
        $this->get('/seo/panel/not-found')->assertOk();
        $this->get('/seo/panel/settings')->assertOk();

        // And the pre-existing per-record editor still works alongside them.
        $post = $this->makePost();
        $this->get("/seo/panel/post/{$post->getKey()}")->assertOk();
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-mau',
        ]);
    }
}
