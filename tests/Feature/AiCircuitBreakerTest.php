<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Ai\AiManager;
use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Exceptions\AiCircuitOpen;
use Duxbo\Seo\Exceptions\AiRequestFailed;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

final class AiCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.ai.cache_ttl', 0);
        $app['config']->set('seo.ai.circuit_breaker.threshold', 3);
        $app['config']->set('seo.ai.drivers.claude.key', 'test-key');
        $app['config']->set('seo.ai.drivers.claude.model', 'claude-sonnet-5');
    }

    public function test_the_circuit_opens_after_the_configured_number_of_consecutive_failures(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'nope'], 500)]);

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->ai()->complete($this->request(), 'claude');
            } catch (AiRequestFailed) {
                // Expected — the real driver failure, not the circuit itself.
            }
        }

        $this->expectException(AiCircuitOpen::class);

        $this->ai()->complete($this->request(), 'claude');
    }

    public function test_an_open_circuit_refuses_the_call_without_ever_touching_the_driver(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'nope'], 500)]);

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->ai()->complete($this->request(), 'claude');
            } catch (AiRequestFailed) {
            }
        }

        $sentSoFar = count(Http::recorded());
        $this->assertGreaterThan(0, $sentSoFar);

        try {
            $this->ai()->complete($this->request(), 'claude');
            $this->fail('Expected AiCircuitOpen.');
        } catch (AiCircuitOpen) {
            // Expected — no new HTTP request should follow.
        }

        Http::assertSentCount($sentSoFar);
    }

    public function test_a_success_resets_the_failure_count(): void
    {
        // Retries off: HttpDriver's own retry-on-5xx would otherwise consume
        // up to 3 queued fakes per *failing* call, throwing off the exact
        // pass/fail sequence this test depends on.
        config(['seo.ai.drivers.claude.retries' => 0]);

        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['error' => 'nope'], 500)
            ->push(['error' => 'nope'], 500)
            ->push([
                'model' => 'claude-sonnet-5',
                'content' => [['type' => 'tool_use', 'input' => ['title' => 'ok']]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200)
            ->push(['error' => 'nope'], 500)
            ->push(['error' => 'nope'], 500),
        ]);

        // Two failures, a success, then two more failures — never three
        // *consecutive* failures, so the circuit (threshold 3) must not open.
        foreach ([false, false, true, false, false] as $shouldSucceed) {
            try {
                $response = $this->ai()->complete($this->request(), 'claude');
                $this->assertTrue($shouldSucceed);
                $this->assertSame('ok', $response->get('title'));
            } catch (AiRequestFailed) {
                $this->assertFalse($shouldSucceed);
            }
        }

        $this->addToAssertionCount(1);
    }

    public function test_the_circuit_breaker_can_be_disabled(): void
    {
        config(['seo.ai.circuit_breaker.enabled' => false]);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'nope'], 500)]);

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->ai()->complete($this->request(), 'claude');
            } catch (AiRequestFailed) {
                // Still fails every time — it just never becomes AiCircuitOpen.
            }
        }

        $this->addToAssertionCount(1);
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
                ],
            ],
        );
    }
}
