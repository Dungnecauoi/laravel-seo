<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ApiTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        // The morph map is static and would otherwise leak into every test
        // that follows, changing what Post::getMorphClass() returns.
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function test_the_api_is_denied_by_default(): void
    {
        // Forgetting to define the Gate must lock the door, not open it: this
        // surface rewrites every title on the site.
        $this->getJson('/api/seo/v1/resolve?url=/x')->assertForbidden();
    }

    public function test_resolve_returns_metadata_for_a_url(): void
    {
        $this->allow();

        $this->getJson('/api/seo/v1/resolve?url=https://trangcuatoi.vn/gioi-thieu')
            ->assertOk()
            ->assertHeader('X-Seo-Contract', '1')
            ->assertJsonPath('title', 'Trang Của Tôi')
            ->assertJsonPath('canonical', 'https://trangcuatoi.vn/gioi-thieu');
    }

    public function test_resolve_can_answer_in_the_shape_next_expects(): void
    {
        $this->allow();

        $this->getJson('/api/seo/v1/resolve?url=https://trangcuatoi.vn/x&format=next')
            ->assertOk()
            ->assertJsonPath('alternates.canonical', 'https://trangcuatoi.vn/x')
            ->assertJsonPath('openGraph.type', 'website');
    }

    public function test_an_unknown_format_is_a_422_naming_the_ones_that_exist(): void
    {
        $this->allow();

        $this->getJson('/api/seo/v1/resolve?url=/x&format=svelte')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'html, array'));
    }

    public function test_meta_can_be_read_and_written(): void
    {
        $this->allow();
        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", [
            'title' => 'Tiêu đề từ API',
            'og' => ['image' => '/anh.jpg'],
        ])->assertOk()->assertJsonPath('resolved.title', 'Tiêu đề từ API');

        $this->getJson("/api/seo/v1/meta/post/{$post->getKey()}")
            ->assertOk()
            ->assertJsonPath('stored.title', 'Tiêu đề từ API');
    }

    public function test_a_type_outside_the_allowlist_is_rejected(): void
    {
        $this->allow();
        config(['seo.api.models' => []]);

        // Without the allowlist this endpoint would enumerate every model in
        // the application by guessing names.
        $this->getJson('/api/seo/v1/meta/post/1')->assertNotFound();
    }

    public function test_writes_are_limited_to_known_fields(): void
    {
        $this->allow();
        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", [
            'title' => 'Hợp lệ',
            'score' => 100,
            'id' => 999,
        ])->assertOk();

        // score and id are not in the validated set, so they never reach storage.
        $this->assertNull(DB::table('seo_meta')->value('score'));
    }

    public function test_analyze_returns_a_score_and_results(): void
    {
        $this->allow();

        $this->postJson('/api/seo/v1/analyze', [
            'content' => '<h1>Tối ưu SEO</h1><p>Nội dung về tối ưu SEO cho website.</p>',
            'keyword' => 'tối ưu SEO',
            'title' => 'Tối ưu SEO cho website',
            'locale' => 'vi',
        ])->assertOk()->assertJsonStructure(['score', 'locale', 'results']);
    }

    public function test_logged_404_data_is_escaped_on_the_way_out(): void
    {
        $this->allow();

        DB::table('seo_not_found')->insert([
            'path' => '/<script>alert(1)</script>',
            'path_hash' => md5('x'),
            'hits' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        // Every field here was supplied by whoever made the request; rendering
        // it raw in a panel is stored XSS.
        $this->getJson('/api/seo/v1/not-found')
            ->assertOk()
            ->assertJsonPath('data.0.path', '/&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    private function allow(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
    }

    private function makePost(): Post
    {
        return Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
    }
}
