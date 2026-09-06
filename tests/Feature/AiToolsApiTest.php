<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Redirects\RedirectRepository;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

/**
 * The REST twin of {@see AiToolsTest} — same registry and dispatcher,
 * exercised through the real `/api/seo/v1/ai/tools` routes instead of
 * calling {@see \Duxbo\Seo\Ai\Tools\AiToolDispatcher} directly.
 */
final class AiToolsApiTest extends TestCase
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
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => true);
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => true);
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function test_the_manifest_lists_tools_with_both_openai_and_anthropic_shaped_schema_keys(): void
    {
        $response = $this->getJson('/api/seo/v1/ai/tools')->assertOk();

        $tool = collect($response->json('tools'))->firstWhere('name', 'seo.dashboard.summary');

        $this->assertNotNull($tool);
        $this->assertSame($tool['input_schema'], $tool['parameters']);
        $this->assertSame('read', $tool['risk_tier']);
    }

    public function test_the_manifest_is_denied_without_the_gate(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => false);

        $this->getJson('/api/seo/v1/ai/tools')->assertForbidden();
    }

    public function test_calling_a_read_tool_returns_ok_immediately(): void
    {
        Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);

        $this->postJson('/api/seo/v1/ai/tools/seo.dashboard.summary/call')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.totalRecords', 1);
    }

    public function test_calling_an_unknown_tool_is_a_404(): void
    {
        $this->postJson('/api/seo/v1/ai/tools/does.not.exist/call')->assertNotFound();
    }

    public function test_a_write_tool_requires_confirm_and_is_denied_without_the_write_gate(): void
    {
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => false);

        $this->postJson('/api/seo/v1/ai/tools/seo.redirects.create/call', [
            'input' => ['source' => '/cu', 'target' => '/moi'],
        ])->assertStatus(403);
    }

    public function test_a_write_tool_proposes_then_applies_over_two_requests(): void
    {
        $proposed = $this->postJson('/api/seo/v1/ai/tools/seo.redirects.create/call', [
            'input' => ['source' => '/cu', 'target' => '/moi'],
        ])->assertOk()->assertJsonPath('status', 'proposed');

        $proposalId = $proposed->json('proposal_id');
        $this->assertDatabaseMissing('seo_redirects', ['source_path' => '/cu']);

        $this->postJson('/api/seo/v1/ai/tools/seo.redirects.create/call', [
            'confirm' => $proposalId,
        ])->assertOk()->assertJsonPath('status', 'applied');

        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/cu', 'target' => '/moi']);
    }

    public function test_an_unsafe_redirect_is_a_422_not_a_500(): void
    {
        $this->postJson('/api/seo/v1/ai/tools/seo.redirects.create/call', [
            'input' => ['source' => '/khuyen-mai', 'target' => 'https://trang-lua-dao.com'],
        ])->assertStatus(422);
    }

    public function test_confirming_a_bogus_proposal_id_is_a_409(): void
    {
        $this->postJson('/api/seo/v1/ai/tools/seo.redirects.create/call', [
            'confirm' => 'not-a-real-id',
        ])->assertStatus(409);
    }

    public function test_a_destructive_tool_actually_deletes_after_confirming(): void
    {
        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');

        $proposed = $this->postJson("/api/seo/v1/ai/tools/seo.redirects.delete/call", [
            'input' => ['id' => $redirect->getKey()],
        ])->assertOk()->assertJsonPath('status', 'proposed');

        $this->postJson('/api/seo/v1/ai/tools/seo.redirects.delete/call', [
            'confirm' => $proposed->json('proposal_id'),
        ])->assertOk()->assertJsonPath('status', 'applied');

        $this->assertDatabaseMissing('seo_redirects', ['id' => $redirect->getKey()]);
    }
}
