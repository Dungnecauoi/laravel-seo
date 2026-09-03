<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The Blade panel's own fetch calls go through session + CSRF (the `web`
 * middleware group), not the bearer-token auth the REST API expects — a
 * same-origin admin page already has both for free.
 */
final class PanelTest extends TestCase
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

        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap(['post' => Post::class]);
    }

    protected function tearDown(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([], false);
        \Illuminate\Database\Eloquent\Relations\Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function test_the_panel_is_denied_by_default(): void
    {
        // Forgetting to define the Gate must lock the door, not open it: the
        // panel can rewrite every title on the site.
        $post = $this->makePost();

        $this->get("/seo/panel/post/{$post->getKey()}")->assertForbidden();
    }

    public function test_the_shell_renders_once_allowed(): void
    {
        $this->allow();
        $post = $this->makePost(['name' => 'Bài viết mẫu']);

        $response = $this->get("/seo/panel/post/{$post->getKey()}");

        $response->assertOk();
        $response->assertSee($post->seoUrl(), false);
        $response->assertSee('id="seo-editor"', false);
    }

    public function test_a_type_outside_the_allowlist_is_rejected(): void
    {
        $this->allow();
        config(['seo.api.models' => []]);

        // The panel shares the API's allowlist; without it either surface
        // would enumerate every model in the application by guessing names.
        $this->get('/seo/panel/post/1')->assertNotFound();
    }

    public function test_reading_and_writing_meta_uses_the_session_not_a_token(): void
    {
        $this->allow();
        $post = $this->makePost();
        $token = $this->csrfToken();

        $this->withSession(['_token' => $token])
            ->putJson("/seo/panel/post/{$post->getKey()}/data", [
                'title' => 'Tiêu đề từ panel',
            ], ['X-CSRF-TOKEN' => $token])
            ->assertOk()
            ->assertJsonPath('resolved.title', 'Tiêu đề từ panel');

        $this->withSession(['_token' => $token])
            ->getJson("/seo/panel/post/{$post->getKey()}/data")
            ->assertOk()
            ->assertJsonPath('stored.title', 'Tiêu đề từ panel');
    }

    public function test_writes_are_limited_to_known_fields(): void
    {
        $this->allow();
        $post = $this->makePost();
        $token = $this->csrfToken();

        $this->withSession(['_token' => $token])
            ->putJson("/seo/panel/post/{$post->getKey()}/data", [
                'title' => 'Hợp lệ',
                'score' => 100,
            ], ['X-CSRF-TOKEN' => $token])
            ->assertOk();

        // 'score' is not in the validated set, so it never reaches storage.
        $this->assertNull(DB::table('seo_meta')->value('score'));
    }

    public function test_analyze_scores_the_submitted_content(): void
    {
        $this->allow();
        $post = $this->makePost();
        $token = $this->csrfToken();

        $this->withSession(['_token' => $token])
            ->postJson("/seo/panel/post/{$post->getKey()}/analyze", [
                'content' => '<h1>Tối ưu SEO</h1><p>Nội dung về tối ưu SEO cho website.</p>',
                'keyword' => 'tối ưu SEO',
                'title' => 'Tối ưu SEO cho website',
                'locale' => 'vi',
            ], ['X-CSRF-TOKEN' => $token])
            ->assertOk()
            ->assertJsonStructure(['score', 'locale', 'results']);
    }

    /**
     * Laravel's CSRF middleware exempts the whole process while PHPUnit is
     * running (`runningInConsole() && runningUnitTests()`), so a token sent
     * here is not actually enforced by this suite — it documents the real
     * request shape rather than proving rejection is possible to test.
     */
    private function csrfToken(): string
    {
        return 'test-csrf-token';
    }

    private function allow(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makePost(array $attributes = []): Post
    {
        return Post::query()->create($attributes + [
            'name' => 'Bài viết mẫu',
            'slug' => 'bai-viet-mau',
        ]);
    }
}
