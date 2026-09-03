<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Ai\AiManager;
use Duxbo\Seo\Ai\Drivers\NullDriver;
use Duxbo\Seo\Contracts\AiDriver;
use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;
use Duxbo\Seo\Exceptions\AiRequestFailed;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class AiTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.ai.cache_ttl', 0);
        $app['config']->set('seo.ai.drivers.claude.key', 'test-key');
        $app['config']->set('seo.ai.drivers.claude.model', 'claude-sonnet-5');
        $app['config']->set('seo.ai.drivers.openai.key', 'test-key');
        $app['config']->set('seo.ai.drivers.openai.model', 'gpt-test');
        $app['config']->set('seo.ai.drivers.gemini.key', 'test-key');
        $app['config']->set('seo.ai.drivers.gemini.model', 'gemini-test');
    }

    public function test_the_default_driver_does_nothing_and_costs_nothing(): void
    {
        // Installing a package must never start billing anyone.
        Http::fake();

        $this->assertInstanceOf(NullDriver::class, $this->ai()->driver());

        Http::assertNothingSent();
    }

    public function test_claude_asks_for_a_tool_call_rather_than_prose(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'Tiêu đề', 'description' => 'Mô tả']]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ])]);

        $response = $this->ai()->complete($this->request(), 'claude');

        $this->assertSame('Tiêu đề', $response->get('title'));
        $this->assertSame(120, $response->totalTokens());

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            // Forcing the tool is what makes the answer a schema-shaped object
            // rather than a sentence describing one.
            $this->assertSame('tool', $body['tool_choice']['type']);
            $this->assertSame('seo_result', $body['tool_choice']['name']);
            $this->assertSame('2023-06-01', $request->header('anthropic-version')[0]);

            return true;
        });
    }

    public function test_openai_pins_the_schema_into_strict_mode(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'model' => 'gpt-test',
            'choices' => [['message' => ['content' => '{"title":"Tiêu đề","description":"Mô tả"}']]],
            'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 10],
        ])]);

        $this->assertSame('Tiêu đề', $this->ai()->complete($this->request(), 'openai')->get('title'));

        Http::assertSent(function ($request): bool {
            $schema = $request->data()['response_format']['json_schema'];

            // Strict mode rejects a schema that allows extra properties or
            // leaves one optional, so both are filled in for the caller.
            $this->assertTrue($schema['strict']);
            $this->assertFalse($schema['schema']['additionalProperties']);
            $this->assertSame(['title', 'description'], $schema['schema']['required']);

            return true;
        });
    }

    public function test_gemini_receives_its_own_schema_dialect(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"title":"Tiêu đề","description":"Mô tả"}']]]]],
            'usageMetadata' => ['promptTokenCount' => 50, 'candidatesTokenCount' => 5],
        ])]);

        $this->assertSame('Tiêu đề', $this->ai()->complete($this->request(), 'gemini')->get('title'));

        Http::assertSent(function ($request): bool {
            $schema = $request->data()['generationConfig']['responseSchema'];

            $this->assertSame('OBJECT', $schema['type']);
            $this->assertSame('STRING', $schema['properties']['title']['type']);
            $this->assertArrayNotHasKey('additionalProperties', $schema);

            // In a header, not the query string, so it cannot land in an
            // access log or a proxy's request record.
            $this->assertSame('test-key', $request->header('x-goog-api-key')[0]);
            $this->assertStringNotContainsString('test-key', $request->url());

            return true;
        });
    }

    public function test_prose_instead_of_structure_is_an_error_not_a_guess(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Sure! Here is a title: ...']],
        ])]);

        $this->expectException(AiRequestFailed::class);
        $this->expectExceptionMessageMatches('/never scraped out of prose/');

        $this->ai()->complete($this->request(), 'claude');
    }

    public function test_a_missing_key_says_where_to_put_one(): void
    {
        config(['seo.ai.drivers.claude.key' => null]);

        $this->expectException(AiRequestFailed::class);
        $this->expectExceptionMessageMatches('/seo\.ai\.drivers\.claude\.key/');

        $this->ai()->complete($this->request(), 'claude');
    }

    public function test_every_call_is_logged_with_its_tokens(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x']]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ])]);

        $this->ai()->complete($this->request(), 'claude', 'meta');

        $this->assertDatabaseHas('seo_ai_log', [
            'driver' => 'claude',
            'purpose' => 'meta',
            'input_tokens' => 100,
            'output_tokens' => 20,
        ]);
    }

    public function test_cost_is_computed_from_configured_rates(): void
    {
        config(['seo.ai.pricing.models' => [
            'claude-sonnet-5' => ['input' => 3.0, 'output' => 15.0],
        ]]);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x']]],
            'usage' => ['input_tokens' => 1_000_000, 'output_tokens' => 1_000_000],
        ])]);

        $this->ai()->complete($this->request(), 'claude');

        // 1M input at $3 plus 1M output at $15. SQLite hands back a float,
        // so the comparison is numeric rather than on the decimal string.
        $this->assertEqualsWithDelta(18.0, (float) DB::table('seo_ai_log')->value('cost'), 0.000001);
    }

    public function test_an_unpriced_model_records_tokens_but_no_cost(): void
    {
        // Better an empty cost column than one that drifts as prices change.
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'some-new-model',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x']]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ])]);

        $this->ai()->complete($this->request(), 'claude');

        $this->assertNull(DB::table('seo_ai_log')->value('cost'));
        $this->assertSame(10, (int) DB::table('seo_ai_log')->value('input_tokens'));
    }

    public function test_the_daily_budget_stops_further_calls(): void
    {
        config(['seo.ai.daily_token_budget' => 100]);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x']]],
            'usage' => ['input_tokens' => 90, 'output_tokens' => 20],
        ])]);

        $this->ai()->complete($this->request(), 'claude');

        // A loop over a few thousand records is easy to write and expensive to
        // run; the cap makes that mistake cost one day, not an invoice.
        $this->expectException(AiRequestFailed::class);
        $this->expectExceptionMessageMatches('/budget is spent/');

        $this->ai()->complete($this->request(), 'claude');
    }

    public function test_the_same_content_is_never_billed_twice(): void
    {
        config(['seo.ai.cache_ttl' => 3600]);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'Tiêu đề']]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ])]);

        $request = $this->request();

        $first = $this->ai()->complete($request, 'claude');
        $second = $this->ai()->complete($request, 'claude');

        $this->assertFalse($first->fromCache);
        $this->assertTrue($second->fromCache);
        $this->assertSame('Tiêu đề', $second->get('title'));

        Http::assertSentCount(1);
    }

    public function test_a_failure_is_recorded_and_rethrown(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'nope'], 500)]);

        try {
            $this->ai()->complete($this->request(), 'claude');
            $this->fail('Expected the request to fail.');
        } catch (AiRequestFailed) {
            // Expected.
        }

        $this->assertNotNull(DB::table('seo_ai_log')->value('error'));
    }

    public function test_a_custom_driver_can_be_registered(): void
    {
        $this->ai()->extend('my-llm', static fn (): AiDriver => new class implements AiDriver
        {
            public function name(): string
            {
                return 'my-llm';
            }

            public function complete(AiRequest $request): AiResponse
            {
                return new AiResponse(['title' => 'Từ mô hình nội bộ'], 'my-llm');
            }
        });

        $response = $this->ai()->complete($this->request(), 'my-llm');

        $this->assertSame('Từ mô hình nội bộ', $response->get('title'));
    }

    public function test_prompts_are_written_in_the_content_language(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'model' => 'claude-sonnet-5',
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x', 'description' => 'y']]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        config(['seo.ai.default' => 'claude']);

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

    private function ai(): AiManager
    {
        return app(AiManager::class);
    }

    private function request(): AiRequest
    {
        return new AiRequest(
            prompt: 'Viết tiêu đề.',
            schema: [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                ],
            ],
        );
    }
}
