<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * Analysis does real work per request — DOMDocument parsing plus every
 * registered check — with no cost control the way the AI budget has one.
 * Both /analyze routes sit behind the viewSeoPanel Gate already; this is
 * defense in depth against a buggy or malicious authenticated client
 * hammering it, not a public-facing limit.
 */
final class AnalyzeRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.analysis.rate_limit', '2,1');
        $app['config']->set('seo.api.enabled', true);
        $app['config']->set('seo.api.models', ['post']);
        $app['config']->set('seo.panel.enabled', true);
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

    public function test_the_api_analyze_route_is_throttled(): void
    {
        $payload = ['content' => 'Nội dung.'];

        $this->postJson('/api/seo/v1/analyze', $payload)->assertOk();
        $this->postJson('/api/seo/v1/analyze', $payload)->assertOk();
        $this->postJson('/api/seo/v1/analyze', $payload)->assertStatus(429);
    }

    public function test_the_panel_analyze_route_is_throttled(): void
    {
        $post = Post::query()->create(['name' => 'X', 'slug' => 'x']);
        $payload = ['content' => 'Nội dung.'];

        $this->postJson("/seo/panel/post/{$post->getKey()}/analyze", $payload)->assertOk();
        $this->postJson("/seo/panel/post/{$post->getKey()}/analyze", $payload)->assertOk();
        $this->postJson("/seo/panel/post/{$post->getKey()}/analyze", $payload)->assertStatus(429);
    }
}
