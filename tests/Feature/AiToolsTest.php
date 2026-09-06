<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Ai\Tools\AiToolDispatcher;
use Duxbo\Seo\Ai\Tools\AiToolRegistry;
use Duxbo\Seo\Audit\AuditBatch;
use Duxbo\Seo\Contracts\MetadataRepository;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolResultStatus;
use Duxbo\Seo\Exceptions\AiToolNotFound;
use Duxbo\Seo\Exceptions\AiToolProposalExpired;
use Duxbo\Seo\Exceptions\AiToolUnauthorized;
use Duxbo\Seo\Exceptions\InvalidSettingValue;
use Duxbo\Seo\Exceptions\UnsafeCanonical;
use Duxbo\Seo\Exceptions\UnsafeRedirect;
use Duxbo\Seo\Facades\Seo;
use Duxbo\Seo\Redirects\RedirectRepository;
use Duxbo\Seo\Settings\SettingsRepository;
use Duxbo\Seo\Tests\Fixtures\FakeDestructiveTool;
use Duxbo\Seo\Tests\Fixtures\FakeWriteTool;
use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

final class AiToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.api.models', ['post']);
        $app['config']->set('seo.ai.default', 'claude');
        $app['config']->set('seo.ai.cache_ttl', 0);
        $app['config']->set('seo.ai.drivers.claude.key', 'test-key');
        $app['config']->set('seo.ai.drivers.claude.model', 'claude-sonnet-5');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Relation::enforceMorphMap(['post' => Post::class]);
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => true);
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => true);

        FakeWriteTool::reset();
        FakeDestructiveTool::reset();
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function test_the_registry_knows_every_configured_tool(): void
    {
        $names = $this->registry()->names();

        $this->assertContains('seo.meta.get', $names);
        $this->assertContains('seo.redirects.list', $names);
        $this->assertContains('seo.not_found.list', $names);
        $this->assertContains('seo.dashboard.summary', $names);
        $this->assertContains('seo.audit.history', $names);
        $this->assertContains('seo.internal_links.list', $names);
        $this->assertContains('seo.settings.get', $names);
    }

    public function test_the_manifest_only_lists_tools_the_caller_is_authorized_for(): void
    {
        $this->registerFakeTools();
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => false);

        $manifest = $this->registry()->manifest(new AiToolContext());
        $names = array_column($manifest, 'name');

        $this->assertContains('seo.dashboard.summary', $names);
        $this->assertNotContains('fake.write', $names);
    }

    public function test_the_manifest_includes_a_write_tool_once_its_gate_is_granted(): void
    {
        $this->registerFakeTools();
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => true);

        $names = array_column($this->registry()->manifest(new AiToolContext()), 'name');

        $this->assertContains('fake.write', $names);
    }

    public function test_calling_an_unknown_tool_throws(): void
    {
        $this->expectException(AiToolNotFound::class);

        $this->dispatcher()->call('does.not.exist', [], new AiToolContext());
    }

    public function test_calling_a_tool_without_the_gate_is_refused(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => false);

        $this->expectException(AiToolUnauthorized::class);

        $this->dispatcher()->call('seo.dashboard.summary', [], new AiToolContext());
    }

    public function test_a_read_tool_executes_immediately(): void
    {
        Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);

        $result = $this->dispatcher()->call('seo.dashboard.summary', [], new AiToolContext());

        $this->assertSame(AiToolResultStatus::Ok, $result->status);
        $this->assertSame(1, $result->data['totalRecords']);
    }

    public function test_a_write_tool_only_mutates_after_the_proposal_is_confirmed(): void
    {
        $this->registerFakeTools();
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => true);

        $context = new AiToolContext();
        $proposed = $this->dispatcher()->call('fake.write', ['value' => 'x'], $context);

        $this->assertSame(AiToolResultStatus::Proposed, $proposed->status);
        $this->assertNotNull($proposed->proposalId);
        $this->assertStringContainsString('Would set value to x', (string) $proposed->preview);
        $this->assertSame([], FakeWriteTool::$calls);

        $applied = $this->dispatcher()->call('fake.write', [], $context, confirm: $proposed->proposalId);

        $this->assertSame(AiToolResultStatus::Applied, $applied->status);
        // The confirm call's own (empty) input is ignored — the input
        // captured at propose time is what actually gets executed.
        $this->assertSame([['value' => 'x']], FakeWriteTool::$calls);
    }

    public function test_confirming_with_an_unknown_proposal_id_is_refused(): void
    {
        $this->registerFakeTools();
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => true);

        $this->expectException(AiToolProposalExpired::class);

        $this->dispatcher()->call('fake.write', [], new AiToolContext(), confirm: 'not-a-real-id');
    }

    public function test_a_destructive_tool_without_a_preview_gets_a_generic_one(): void
    {
        $this->registerFakeTools();
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => true);

        $proposed = $this->dispatcher()->call('fake.destructive', [], new AiToolContext());

        $this->assertSame('No dry-run preview is available for this action.', $proposed->preview);
        $this->assertSame(0, FakeDestructiveTool::$calls);
    }

    public function test_propose_and_apply_are_both_recorded_in_the_audit_table(): void
    {
        $this->registerFakeTools();
        Gate::define('useSeoAiWrites', static fn (mixed $user = null): bool => true);

        $context = new AiToolContext();
        $proposed = $this->dispatcher()->call('fake.write', ['value' => 'x'], $context);
        $this->dispatcher()->call('fake.write', [], $context, confirm: $proposed->proposalId);

        $rows = DB::table('seo_ai_tool_calls')->where('proposal_id', $proposed->proposalId)->orderBy('id')->get();

        $this->assertCount(2, $rows);
        $this->assertSame('proposed', $rows[0]->status);
        $this->assertSame('applied', $rows[1]->status);
        $this->assertNotNull($rows[1]->applied_at);
    }

    public function test_get_meta_tool_returns_stored_and_resolved_data(): void
    {
        $post = Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
        Seo::save($post, ['title' => 'Tiêu đề tuỳ chỉnh']);

        $result = $this->dispatcher()->call('seo.meta.get', ['type' => 'post', 'id' => (string) $post->id], new AiToolContext());

        $this->assertSame('Tiêu đề tuỳ chỉnh', $result->data['stored']['title']);
        $this->assertSame('Tiêu đề tuỳ chỉnh', $result->data['resolved']['title']);
    }

    public function test_list_redirects_tool_returns_created_rules(): void
    {
        app(RedirectRepository::class)->create('/cu', '/moi');

        $result = $this->dispatcher()->call('seo.redirects.list', [], new AiToolContext());

        $this->assertSame('/cu', $result->data['data'][0]['source']);
    }

    public function test_list_not_found_tool_returns_logged_paths(): void
    {
        DB::table('seo_not_found')->insert([
            'path' => '/cu', 'path_hash' => md5('/cu'), 'hits' => 5,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $result = $this->dispatcher()->call('seo.not_found.list', [], new AiToolContext());

        $this->assertSame('/cu', $result->data['data'][0]['path']);
        $this->assertSame(5, $result->data['data'][0]['hits']);
    }

    public function test_audit_history_tool_returns_past_batches(): void
    {
        AuditBatch::query()->create([
            'model' => Post::class,
            'total_records' => 10,
            'average_score' => 82.5,
            'min_score' => 40,
            'max_score' => 100,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $result = $this->dispatcher()->call('seo.audit.history', [], new AiToolContext());

        $this->assertSame(Post::class, $result->data['data'][0]['model']);
        $this->assertEquals(82.5, $result->data['data'][0]['averageScore']);
    }

    public function test_list_internal_links_tool_flags_an_orphan(): void
    {
        Post::query()->create(['name' => 'Mồ côi', 'slug' => 'mo-coi']);

        $result = $this->dispatcher()->call('seo.internal_links.list', ['type' => 'post'], new AiToolContext());

        $this->assertSame('post', $result->data['type']);
        $this->assertTrue($result->data['data'][0]['isOrphan']);
        $this->assertSame(0, $result->data['data'][0]['incomingLinks']);
    }

    public function test_get_settings_tool_masks_secret_keys(): void
    {
        config(['seo.settings.enabled' => true]);
        app(SettingsRepository::class)->set('search_console.refresh_token', 'super-secret');

        $result = $this->dispatcher()->call('seo.settings.get', [], new AiToolContext());
        $entry = $result->data['settings']['search_console.refresh_token'];

        $this->assertTrue($entry['secret']);
        $this->assertTrue($entry['is_set']);
        $this->assertArrayNotHasKey('value', $entry);
    }

    public function test_create_redirect_tool_proposes_then_creates(): void
    {
        $context = new AiToolContext();

        $proposed = $this->dispatcher()->call('seo.redirects.create', [
            'source' => '/cu', 'target' => '/moi',
        ], $context);

        $this->assertSame(AiToolResultStatus::Proposed, $proposed->status);
        $this->assertStringContainsString('301', (string) $proposed->preview);
        $this->assertDatabaseMissing('seo_redirects', ['source_path' => '/cu']);

        $applied = $this->dispatcher()->call('seo.redirects.create', [], $context, confirm: $proposed->proposalId);

        $this->assertSame(AiToolResultStatus::Applied, $applied->status);
        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/cu', 'target' => '/moi']);
    }

    public function test_create_redirect_tool_refuses_an_unsafe_target_on_the_propose_call(): void
    {
        $this->expectException(UnsafeRedirect::class);

        // Fails on the very first (propose) call — no proposal is ever
        // created for a rule that was always going to be refused.
        $this->dispatcher()->call('seo.redirects.create', [
            'source' => '/khuyen-mai', 'target' => 'https://trang-lua-dao.com',
        ], new AiToolContext());

        $this->assertSame(0, DB::table('seo_ai_tool_calls')->count());
    }

    public function test_toggle_redirect_tool_disables_an_active_rule(): void
    {
        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');
        $context = new AiToolContext();

        $proposed = $this->dispatcher()->call('seo.redirects.toggle', [
            'id' => $redirect->getKey(), 'active' => false,
        ], $context);

        $this->dispatcher()->call('seo.redirects.toggle', [], $context, confirm: $proposed->proposalId);

        $this->assertDatabaseHas('seo_redirects', ['id' => $redirect->getKey(), 'is_active' => false]);
    }

    public function test_toggle_redirect_tool_refuses_an_unknown_id_immediately(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->dispatcher()->call('seo.redirects.toggle', ['id' => 999, 'active' => true], new AiToolContext());
    }

    public function test_delete_redirect_tool_removes_the_row_only_after_confirming(): void
    {
        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');
        $context = new AiToolContext();

        $proposed = $this->dispatcher()->call('seo.redirects.delete', ['id' => $redirect->getKey()], $context);
        $this->assertDatabaseHas('seo_redirects', ['id' => $redirect->getKey()]);

        $this->dispatcher()->call('seo.redirects.delete', [], $context, confirm: $proposed->proposalId);
        $this->assertDatabaseMissing('seo_redirects', ['id' => $redirect->getKey()]);
    }

    public function test_prune_not_found_tool_preview_counts_exactly_what_it_will_delete(): void
    {
        DB::table('seo_not_found')->insert([
            ['path' => '/cu', 'path_hash' => md5('/cu'), 'hits' => 1, 'first_seen_at' => now()->subDays(200), 'last_seen_at' => now()->subDays(200)],
            ['path' => '/moi', 'path_hash' => md5('/moi'), 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()],
        ]);

        $context = new AiToolContext();
        $proposed = $this->dispatcher()->call('seo.not_found.prune', ['days' => 90], $context);

        $this->assertStringContainsString('1 404 log entry', (string) $proposed->preview);

        $applied = $this->dispatcher()->call('seo.not_found.prune', [], $context, confirm: $proposed->proposalId);

        $this->assertSame(1, $applied->data['deleted']);
        $this->assertDatabaseMissing('seo_not_found', ['path' => '/cu']);
        $this->assertDatabaseHas('seo_not_found', ['path' => '/moi']);
    }

    public function test_convert_not_found_to_redirect_tool_creates_a_redirect_and_clears_the_log(): void
    {
        DB::table('seo_not_found')->insert([
            'id' => 1, 'path' => '/duong-dan-cu', 'path_hash' => md5('/duong-dan-cu'),
            'hits' => 5, 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $context = new AiToolContext();
        $proposed = $this->dispatcher()->call('seo.not_found.convert_to_redirect', [
            'id' => 1, 'target' => '/duong-dan-moi',
        ], $context);

        $this->dispatcher()->call('seo.not_found.convert_to_redirect', [], $context, confirm: $proposed->proposalId);

        $this->assertDatabaseHas('seo_redirects', ['source_path' => '/duong-dan-cu', 'target' => '/duong-dan-moi']);
        $this->assertDatabaseMissing('seo_not_found', ['id' => 1]);
    }

    public function test_set_setting_tool_writes_a_valid_value_after_confirming(): void
    {
        config(['seo.settings.enabled' => true]);
        $context = new AiToolContext();

        $proposed = $this->dispatcher()->call('seo.settings.set', [
            'key' => 'verification.google', 'value' => 'abc123',
        ], $context);

        $this->assertNull(config('seo.verification.google'));

        $this->dispatcher()->call('seo.settings.set', [], $context, confirm: $proposed->proposalId);

        $this->assertSame('abc123', config('seo.verification.google'));
    }

    public function test_set_setting_tool_refuses_an_invalid_value_on_the_propose_call(): void
    {
        config(['seo.settings.enabled' => true]);

        $this->expectException(InvalidSettingValue::class);

        $this->dispatcher()->call('seo.settings.set', [
            'key' => 'robots.block_ai_crawlers', 'value' => 'not-a-boolean',
        ], new AiToolContext());
    }

    public function test_set_setting_tool_never_echoes_a_secret_value_in_the_preview(): void
    {
        config(['seo.settings.enabled' => true]);

        $proposed = $this->dispatcher()->call('seo.settings.set', [
            'key' => 'search_console.refresh_token', 'value' => 'super-secret-token',
        ], new AiToolContext());

        $this->assertStringNotContainsString('super-secret-token', (string) $proposed->preview);
    }

    public function test_clear_setting_tool_removes_a_stored_override(): void
    {
        config(['seo.settings.enabled' => true]);
        app(SettingsRepository::class)->set('verification.google', 'abc123');

        $context = new AiToolContext();
        $proposed = $this->dispatcher()->call('seo.settings.clear', ['key' => 'verification.google'], $context);
        $this->dispatcher()->call('seo.settings.clear', [], $context, confirm: $proposed->proposalId);

        $this->assertFalse(app(SettingsRepository::class)->has('verification.google'));
    }

    public function test_submit_urls_tool_posts_to_indexnow_only_after_confirming(): void
    {
        config(['seo.indexnow.enabled' => true, 'seo.indexnow.key' => 'test-key-123']);
        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);

        $context = new AiToolContext();
        $proposed = $this->dispatcher()->call('seo.indexnow.submit', ['urls' => ['/bai-viet-1']], $context);

        Http::assertNothingSent();

        $applied = $this->dispatcher()->call('seo.indexnow.submit', [], $context, confirm: $proposed->proposalId);

        $this->assertTrue($applied->data['submitted']);
        Http::assertSentCount(1);
    }

    public function test_suggest_meta_tool_grounds_the_prompt_in_stored_metadata_and_calls_ai(): void
    {
        $post = Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
        Seo::save($post, ['title' => 'Tiêu đề đã lưu']);
        $this->fakeClaude(['title' => 'Tiêu đề mới', 'description' => 'Mô tả mới']);

        $result = $this->dispatcher()->call('seo.meta.suggest', [
            'type' => 'post', 'id' => (string) $post->id, 'content' => '<p>Nội dung</p>',
        ], new AiToolContext());

        $this->assertSame('Tiêu đề mới', $result->data['title']);

        Http::assertSent(function ($request): bool {
            $this->assertStringContainsString('Tiêu đề đã lưu', $request->data()['messages'][0]['content']);

            return true;
        });
    }

    public function test_apply_meta_tool_writes_only_after_confirming(): void
    {
        $post = Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
        $context = new AiToolContext();

        $proposed = $this->dispatcher()->call('seo.meta.apply', [
            'type' => 'post', 'id' => (string) $post->id, 'title' => 'Tiêu đề AI đề xuất',
        ], $context);

        $this->assertNotSame('Tiêu đề AI đề xuất', Seo::for($post)->title);

        $this->dispatcher()->call('seo.meta.apply', [], $context, confirm: $proposed->proposalId);

        $this->assertSame('Tiêu đề AI đề xuất', Seo::for($post)->title);
    }

    public function test_apply_meta_tool_refuses_an_off_site_canonical_on_the_propose_call(): void
    {
        $post = Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);

        $this->expectException(UnsafeCanonical::class);

        $this->dispatcher()->call('seo.meta.apply', [
            'type' => 'post', 'id' => (string) $post->id, 'canonical' => 'https://trang-lua-dao.com',
        ], new AiToolContext());
    }

    public function test_delete_meta_tool_deletes_only_after_confirming(): void
    {
        $post = Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
        Seo::save($post, ['title' => 'Tiêu đề']);
        $context = new AiToolContext();

        $proposed = $this->dispatcher()->call('seo.meta.delete', ['type' => 'post', 'id' => (string) $post->id], $context);
        $this->assertNotNull($this->app->make(MetadataRepository::class)->find($post));

        $this->dispatcher()->call('seo.meta.delete', [], $context, confirm: $proposed->proposalId);
        $this->assertNull($this->app->make(MetadataRepository::class)->find($post));
    }

    public function test_suggest_content_fixes_tool_grounds_the_prompt_in_real_analysis_findings(): void
    {
        $post = Post::query()->create(['name' => 'Bài viết mẫu', 'slug' => 'bai-viet-mau']);
        $this->fakeClaude(['title' => 'Tiêu đề sửa', 'description' => 'Mô tả sửa']);

        // A handful of words is well under any content-length minimum.
        $result = $this->dispatcher()->call('seo.analysis.suggest_fixes', [
            'type' => 'post', 'id' => (string) $post->id, 'content' => '<p>Rất ngắn.</p>', 'locale' => 'vi',
        ], new AiToolContext());

        $this->assertSame('Tiêu đề sửa', $result->data['title']);
        $this->assertContains('content-length', $result->data['addressedProblems']);

        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][0]['content'];
            $this->assertStringNotContainsString('seo::analysis', $prompt);

            return true;
        });
    }

    public function test_suggest_redirect_target_tool_builds_real_candidates_from_existing_posts(): void
    {
        Post::query()->create(['name' => 'Hướng dẫn SEO', 'slug' => 'huong-dan-seo']);
        Post::query()->create(['name' => 'Công thức nấu ăn', 'slug' => 'cong-thuc-nau-an']);

        DB::table('seo_not_found')->insert([
            'id' => 1, 'path' => '/huong-dan-seo-cu', 'path_hash' => md5('/huong-dan-seo-cu'),
            'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $this->fakeClaude(['targetUrl' => 'https://trangcuatoi.vn/bai-viet/huong-dan-seo', 'reasoning' => 'Khớp từ khoá']);

        $result = $this->dispatcher()->call('seo.not_found.suggest_redirect_target', ['id' => 1], new AiToolContext());

        $this->assertSame('https://trangcuatoi.vn/bai-viet/huong-dan-seo', $result->data['targetUrl']);
        $this->assertNotEmpty($result->data['candidates']);

        Http::assertSent(function ($request): bool {
            $schema = $request->data()['tools'][0]['input_schema'];

            // The unrelated recipe post must not even be offered as a candidate.
            $this->assertContains('https://trangcuatoi.vn/bai-viet/huong-dan-seo', $schema['properties']['targetUrl']['enum']);
            $this->assertNotContains('https://trangcuatoi.vn/bai-viet/cong-thuc-nau-an', $schema['properties']['targetUrl']['enum']);

            return true;
        });
    }

    public function test_suggest_internal_link_fixes_tool_builds_real_candidates_from_sibling_posts(): void
    {
        $orphan = Post::query()->create(['name' => 'Hướng dẫn SEO nâng cao', 'slug' => 'huong-dan-seo-nang-cao']);
        $related = Post::query()->create(['name' => 'Hướng dẫn SEO cơ bản', 'slug' => 'huong-dan-seo-co-ban']);
        Post::query()->create(['name' => 'Công thức nấu ăn', 'slug' => 'cong-thuc-nau-an']);

        $this->fakeClaude(['suggestions' => [
            ['sourceUrl' => $related->seoUrl(), 'anchorText' => 'hướng dẫn SEO cơ bản'],
        ]]);

        $result = $this->dispatcher()->call('seo.internal_links.suggest_fixes', [
            'type' => 'post', 'id' => (string) $orphan->id,
        ], new AiToolContext());

        $this->assertSame($related->seoUrl(), $result->data['suggestions'][0]['sourceUrl']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function fakeClaude(array $input): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => $input]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);
    }

    private function registerFakeTools(): void
    {
        config(['seo.ai.tools.enabled' => array_merge(
            config('seo.ai.tools.enabled', []),
            [FakeWriteTool::class, FakeDestructiveTool::class],
        )]);
    }

    private function registry(): AiToolRegistry
    {
        return app(AiToolRegistry::class);
    }

    private function dispatcher(): AiToolDispatcher
    {
        return app(AiToolDispatcher::class);
    }
}
