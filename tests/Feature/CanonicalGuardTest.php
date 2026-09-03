<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A canonical set to an outside domain tells search engines this page's real
 * home is elsewhere and can pull it out of the index — the same class of
 * mistake as an open redirect, just quieter. Both API and Panel share the
 * same SameOriginUrls check the redirect guard already used.
 */
final class CanonicalGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.api.enabled', true);
        $app['config']->set('seo.api.models', ['post']);
        $app['config']->set('seo.panel.enabled', true);
        $app['config']->set('app.url', 'http://localhost');
    }

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap(['post' => Post::class]);
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
    }

    protected function tearDown(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([], false);
        \Illuminate\Database\Eloquent\Relations\Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function test_the_api_rejects_a_canonical_on_another_domain(): void
    {
        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", [
            'canonical' => 'https://mot-trang-khac.com/bai-viet',
        ])->assertStatus(422)->assertJsonValidationErrors('canonical');

        $this->assertNull(DB::table('seo_meta')->value('canonical_url'));
    }

    public function test_the_panel_rejects_a_canonical_on_another_domain(): void
    {
        $post = $this->makePost();

        $this->putJson("/seo/panel/post/{$post->getKey()}/data", [
            'canonical' => 'https://mot-trang-khac.com/bai-viet',
        ])->assertStatus(422)->assertJsonValidationErrors('canonical');
    }

    public function test_a_path_canonical_is_accepted(): void
    {
        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", [
            'canonical' => '/bai-viet-goc',
        ])->assertOk();

        $this->assertSame('/bai-viet-goc', DB::table('seo_meta')->value('canonical_url'));
    }

    public function test_a_canonical_on_the_apps_own_host_is_accepted(): void
    {
        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", [
            'canonical' => 'http://localhost/bai-viet-goc',
        ])->assertOk();
    }

    public function test_an_allowlisted_external_host_is_accepted(): void
    {
        config(['seo.redirects.allowed_hosts' => ['cdn.example.com']]);

        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", [
            'canonical' => 'https://cdn.example.com/bai-viet-goc',
        ])->assertOk();
    }

    public function test_a_protocol_relative_canonical_is_rejected(): void
    {
        // "//evil.com" looks like a path and is not one.
        $post = $this->makePost();

        $this->putJson("/api/seo/v1/meta/post/{$post->getKey()}", [
            'canonical' => '//mot-trang-khac.com/bai-viet',
        ])->assertStatus(422)->assertJsonValidationErrors('canonical');
    }

    private function makePost(): Post
    {
        return Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
    }
}
