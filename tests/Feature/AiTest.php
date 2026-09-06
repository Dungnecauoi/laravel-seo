<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Ai\SeoAiManager;
use Duxbo\Seo\Data\CheckResult;
use Duxbo\Seo\Data\SeoData;
use Duxbo\Seo\SeoServiceProvider;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * What SEO owns about AI is the prompt itself — what goes into it, in which
 * language, grounded in how much real context — plus wiring its own
 * `seo.ai_overrides` config into the shared `duxbo/laravel-ai-core` layer.
 * Driver behaviour (schema-forcing, budgeting, caching, the circuit
 * breaker) is ai-core's own responsibility and is tested in that package's
 * own suite, not duplicated here.
 */
final class AiTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('ai-core.default', 'claude');
        $app['config']->set('ai-core.cache_ttl', 0);
        $app['config']->set('ai-core.drivers.claude.key', 'test-key');
        $app['config']->set('ai-core.drivers.claude.model', 'claude-sonnet-5');
    }

    public function test_prompts_are_written_in_the_content_language(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x', 'description' => 'y']]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $this->ai()->suggestMeta('<p>Nội dung tiếng Việt</p>', 'tối ưu SEO', 'vi');

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            // An English instruction asking for a Vietnamese description
            // reliably produces stilted Vietnamese.
            $this->assertStringContainsString('Viết tiêu đề trang', $body['messages'][0]['content']);
            $this->assertStringContainsString('tối ưu SEO', $body['messages'][0]['content']);

            // Markup is noise the model pays for by the token.
            $this->assertStringNotContainsString('<p>', $body['messages'][0]['content']);

            return true;
        });
    }

    public function test_suggest_meta_without_grounding_produces_the_same_prompt_as_before(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x', 'description' => 'y']]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $this->ai()->suggestMeta('<p>Nội dung</p>', 'tối ưu SEO', 'vi');

        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][0]['content'];

            // No current/siteBrand/findings passed — the prompt must not
            // grow an empty "Additional context:" section.
            $this->assertStringNotContainsString('Bối cảnh bổ sung', $prompt);

            return true;
        });
    }

    public function test_suggest_meta_grounds_the_prompt_in_the_current_metadata_and_site_brand(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x', 'description' => 'y']]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $this->ai()->suggestMeta(
            '<p>Nội dung</p>',
            'tối ưu SEO',
            'vi',
            current: new SeoData(title: 'Tiêu đề cũ', description: 'Mô tả cũ'),
            siteBrand: 'Trang Của Tôi',
        );

        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][0]['content'];

            $this->assertStringContainsString('Bối cảnh bổ sung', $prompt);
            $this->assertStringContainsString('Trang Của Tôi', $prompt);
            $this->assertStringContainsString('Tiêu đề cũ', $prompt);
            $this->assertStringContainsString('Mô tả cũ', $prompt);

            return true;
        });
    }

    public function test_suggest_content_fixes_grounds_the_prompt_in_real_translated_findings(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x', 'description' => 'y']]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $finding = CheckResult::warning(
            'content-length',
            'seo::analysis.content_length.short',
            'seo::analysis.content_length.hint',
            ['count' => 120, 'minimum' => 600],
        );

        $this->ai()->suggestContentFixes('<p>Nội dung</p>', [$finding], keyword: 'tối ưu SEO', locale: 'vi');

        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][0]['content'];

            // The raw translation key must never leak into the prompt —
            // only its rendered, human-readable form.
            $this->assertStringNotContainsString('seo::analysis.content_length.short', $prompt);
            $this->assertStringContainsString('120', $prompt);

            return true;
        });
    }

    public function test_suggest_redirect_target_constrains_the_answer_to_the_real_candidates(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['targetUrl' => '/bai-moi', 'reasoning' => 'Khớp nhất']]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $result = $this->ai()->suggestRedirectTarget('/bai-cu', [
            ['url' => '/bai-moi', 'title' => 'Bài viết mới'],
            ['url' => '/khac', 'title' => null],
        ]);

        $this->assertSame('/bai-moi', $result['targetUrl']);

        Http::assertSent(function ($request): bool {
            $schema = $request->data()['tools'][0]['input_schema'];

            $this->assertSame(['/bai-moi', '/khac'], $schema['properties']['targetUrl']['enum']);

            $prompt = $request->data()['messages'][0]['content'];
            $this->assertStringContainsString('/bai-moi', $prompt);
            $this->assertStringContainsString('Bài viết mới', $prompt);

            return true;
        });
    }

    public function test_suggest_internal_link_fixes_constrains_source_urls_to_the_real_candidates(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['suggestions' => [
                ['sourceUrl' => '/lien-quan', 'anchorText' => 'bài viết liên quan'],
            ]]]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        $result = $this->ai()->suggestInternalLinkFixes('/mo-coi', 'Bài mồ côi', [
            ['url' => '/lien-quan', 'title' => 'Bài liên quan'],
        ]);

        $this->assertSame('/lien-quan', $result['suggestions'][0]['sourceUrl']);

        Http::assertSent(function ($request): bool {
            $schema = $request->data()['tools'][0]['input_schema'];

            $this->assertSame(
                ['/lien-quan'],
                $schema['properties']['suggestions']['items']['properties']['sourceUrl']['enum'],
            );

            return true;
        });
    }

    public function test_seo_ai_overrides_are_pushed_into_its_own_ai_core_profile(): void
    {
        config(['seo.ai_overrides' => [
            'drivers' => ['claude' => ['model' => 'claude-seo-special']],
        ]]);

        // Re-runs the exact registration-time push SeoServiceProvider makes
        // on every real boot, now that the override above is in place.
        $this->app->register(SeoServiceProvider::class, force: true);

        $this->assertSame(
            'claude-seo-special',
            config('ai-core.profiles.seo.drivers.claude.model'),
        );
    }

    public function test_with_no_overrides_seo_leaves_ai_cores_profile_untouched(): void
    {
        $this->assertSame([], config('ai-core.profiles.seo', []));
    }

    private function ai(): SeoAiManager
    {
        return app(SeoAiManager::class);
    }
}
