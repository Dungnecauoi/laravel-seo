<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Redirects\RedirectRepository;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;

/**
 * End-to-end tests for {@see \Duxbo\Seo\Mcp\McpServer} through the real
 * `/api/seo/v1/mcp` route — JSON-RPC 2.0 per
 * https://modelcontextprotocol.io/specification/2025-06-18/basic/transports,
 * the non-streaming `application/json` half of it.
 */
final class McpTest extends TestCase
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

    public function test_initialize_negotiates_the_protocol_version_and_describes_the_server(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-06-18'], id: 1)
            ->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('id', 1)
            ->assertJsonPath('result.protocolVersion', '2025-06-18')
            ->assertJsonPath('result.serverInfo.name', 'duxbo/laravel-seo')
            ->assertJsonPath('result.capabilities.tools.listChanged', false);
    }

    public function test_initialize_falls_back_to_the_default_version_for_an_unrecognised_request(): void
    {
        $this->rpc('initialize', ['protocolVersion' => 'not-a-real-version'], id: 1)
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2025-06-18');
    }

    public function test_a_notification_gets_202_and_no_body(): void
    {
        $response = $this->postJson('/api/seo/v1/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $response->assertStatus(202);
        $this->assertSame('', $response->getContent());
    }

    public function test_ping_returns_an_empty_result_object(): void
    {
        // Not assertExactJson(): it canonicalises through an assoc-array
        // decode, which erases the very {} vs [] distinction this checks —
        // an empty PHP array encodes as JSON "{}" only when it is genuinely
        // a stdClass on the wire, which the raw content string confirms.
        $response = $this->rpc('ping', [], id: 1)->assertOk();

        $this->assertSame('{"jsonrpc":"2.0","id":1,"result":{}}', $response->getContent());
    }

    public function test_tools_list_returns_camel_case_input_schema_and_risk_annotations(): void
    {
        $response = $this->rpc('tools/list', [], id: 1)->assertOk();

        $tool = collect($response->json('result.tools'))->firstWhere('name', 'seo.dashboard.summary');

        $this->assertNotNull($tool);
        $this->assertArrayHasKey('inputSchema', $tool);
        $this->assertTrue($tool['annotations']['readOnlyHint']);
        $this->assertFalse($tool['annotations']['destructiveHint']);
    }

    public function test_tools_list_only_includes_tools_the_caller_is_authorized_for(): void
    {
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => false);

        $response = $this->rpc('tools/list', [], id: 1)->assertOk();
        $names = collect($response->json('result.tools'))->pluck('name');

        $this->assertTrue($names->contains('seo.dashboard.summary'));
        $this->assertFalse($names->contains('seo.redirects.delete'));
    }

    public function test_tools_call_on_a_read_tool_returns_structured_content(): void
    {
        Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);

        $response = $this->rpc('tools/call', ['name' => 'seo.dashboard.summary', 'arguments' => []], id: 1)
            ->assertOk();

        $response->assertJsonPath('result.isError', false);
        $this->assertSame(1, $response->json('result.structuredContent.data.totalRecords'));
        $this->assertIsString($response->json('result.content.0.text'));
    }

    public function test_tools_call_on_an_unknown_tool_is_a_protocol_error(): void
    {
        $this->rpc('tools/call', ['name' => 'does.not.exist', 'arguments' => []], id: 1)
            ->assertOk()
            ->assertJsonPath('error.code', -32602)
            ->assertJsonMissingPath('result');
    }

    public function test_tools_call_without_the_write_gate_is_a_protocol_error(): void
    {
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => false);

        $this->rpc('tools/call', [
            'name' => 'seo.redirects.create',
            'arguments' => ['source' => '/cu', 'target' => '/moi'],
        ], id: 1)->assertOk()->assertJsonPath('error.code', -32000);
    }

    public function test_a_business_logic_refusal_is_a_tool_error_not_a_protocol_error(): void
    {
        // isError: true inside a normal "result", not a JSON-RPC "error" —
        // the spec's own distinction between protocol errors and tool
        // execution errors.
        $this->rpc('tools/call', [
            'name' => 'seo.redirects.create',
            'arguments' => ['source' => '/khuyen-mai', 'target' => 'https://trang-lua-dao.com'],
        ], id: 1)
            ->assertOk()
            ->assertJsonMissingPath('error')
            ->assertJsonPath('result.isError', true);
    }

    public function test_tools_call_proposes_then_applies_via_the_confirm_argument(): void
    {
        $proposed = $this->rpc('tools/call', [
            'name' => 'seo.redirects.create',
            'arguments' => ['source' => '/cu', 'target' => '/moi'],
        ], id: 1)->assertOk();

        $proposalId = $proposed->json('result.structuredContent.proposal_id');
        $this->assertNotNull($proposalId);
        $this->assertDatabaseMissing('seo_redirects', ['source_path' => '/cu']);

        $this->rpc('tools/call', [
            'name' => 'seo.redirects.create',
            'arguments' => ['confirm' => $proposalId],
        ], id: 2)
            ->assertOk()
            ->assertJsonPath('result.structuredContent.status', 'applied');

        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/cu', 'target' => '/moi']);
    }

    public function test_an_unsupported_protocol_version_header_is_a_400(): void
    {
        $this->withHeaders(['MCP-Protocol-Version' => '1999-01-01'])
            ->postJson('/api/seo/v1/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
            ->assertStatus(400);
    }

    public function test_a_malformed_body_is_a_400(): void
    {
        $this->postJson('/api/seo/v1/mcp', ['id' => 1, 'method' => 'ping'])->assertStatus(400);
    }

    public function test_get_and_delete_are_405(): void
    {
        $this->getJson('/api/seo/v1/mcp')->assertStatus(405);
        $this->deleteJson('/api/seo/v1/mcp')->assertStatus(405);
    }

    public function test_destructive_delete_actually_deletes_after_confirming(): void
    {
        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');

        $proposed = $this->rpc('tools/call', [
            'name' => 'seo.redirects.delete',
            'arguments' => ['id' => $redirect->getKey()],
        ], id: 1)->assertOk();

        $this->rpc('tools/call', [
            'name' => 'seo.redirects.delete',
            'arguments' => ['confirm' => $proposed->json('result.structuredContent.proposal_id')],
        ], id: 2)->assertOk()->assertJsonPath('result.structuredContent.status', 'applied');

        $this->assertDatabaseMissing('seo_redirects', ['id' => $redirect->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function rpc(string $method, array $params, int $id): TestResponse
    {
        return $this->postJson('/api/seo/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ]);
    }
}
