<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Ai\AiManager;
use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Redirects\RedirectRepository;
use Duxbo\Seo\Settings\SettingsRepository;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * Under ordinary PHP-FPM every singleton this package registers is rebuilt
 * fresh per request, so none of this matters. Under a long-running worker
 * (Laravel Octane) the same instances persist for the worker's whole life —
 * these tests simulate that by holding one instance across several
 * assertions and calling resetForNewRequest() directly, the same call the
 * provider's Octane listener makes; dispatching the real Octane event by
 * its class *name* (Octane itself is not installed in this suite) confirms
 * the listener is actually wired to it.
 */
final class OctaneSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.redirects.cache_ttl', 3600);
        $app['config']->set('app.url', 'http://localhost');
    }

    public function test_redirect_matcher_keeps_serving_a_stale_target_until_reset(): void
    {
        $repository = $this->app->make(RedirectRepository::class);
        /** @var RedirectMatcher $matcher */
        $matcher = $this->app->make(RedirectMatcher::class);

        $repository->create('/cu', '/moi-1');
        $this->assertSame('/moi-1', $matcher->match('/cu')?->target);

        // Simulate a different worker process's edit: bypass the repository
        // (and therefore its flush()) entirely, the way another process's
        // own matcher instance would after correctly invalidating the
        // shared cache from its own flush() call.
        $this->app->make(Cache::class)->forget('seo:redirects');
        Redirect::query()->where('source_path', '/cu')->update(['target' => '/moi-2']);

        // This worker's in-memory copy has not been told anything changed.
        $this->assertSame('/moi-1', $matcher->match('/cu')?->target);
    }

    public function test_reset_for_new_request_makes_the_matcher_see_the_edit(): void
    {
        $repository = $this->app->make(RedirectRepository::class);
        /** @var RedirectMatcher $matcher */
        $matcher = $this->app->make(RedirectMatcher::class);

        $repository->create('/cu', '/moi-1');
        $matcher->match('/cu');

        $this->app->make(Cache::class)->forget('seo:redirects');
        Redirect::query()->where('source_path', '/cu')->update(['target' => '/moi-2']);

        $matcher->resetForNewRequest();

        $this->assertSame('/moi-2', $matcher->match('/cu')?->target);
    }

    public function test_ai_manager_rebuilds_a_driver_from_current_config_after_reset(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'input' => ['title' => 'x']]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ])]);

        config([
            'seo.ai.drivers.claude.key' => 'old-key',
            'seo.ai.drivers.claude.model' => 'claude-old',
            'seo.ai.cache_ttl' => 0,
        ]);

        $manager = $this->app->make(AiManager::class);
        $first = $manager->driver('claude');

        config(['seo.ai.drivers.claude.model' => 'claude-new']);
        $sameInstance = $manager->driver('claude');

        // Without a reset, the memoized instance is still the one built
        // from the old config — the config change alone changes nothing.
        $this->assertSame($first, $sameInstance);

        $manager->resetForNewRequest();
        $rebuilt = $manager->driver('claude');

        $this->assertNotSame($first, $rebuilt);
    }

    public function test_settings_repository_reapplies_overrides_to_the_live_config(): void
    {
        config(['seo.settings.enabled' => true]);

        $settings = $this->app->make(SettingsRepository::class);
        $settings->set('verification.google', 'abc123');

        // A direct config() mutation, bypassing the repository entirely —
        // simulating this worker having booted before the override existed,
        // the exact situation resetForNewRequest() exists to correct.
        config(['seo.verification.google' => null]);
        $this->assertNull(config('seo.verification.google'));

        $settings->resetForNewRequest();

        $this->assertSame('abc123', config('seo.verification.google'));
    }

    public function test_the_octane_request_received_event_triggers_every_reset(): void
    {
        config(['seo.redirects.cache_ttl' => 3600]);

        $repository = $this->app->make(RedirectRepository::class);
        /** @var RedirectMatcher $matcher */
        $matcher = $this->app->make(RedirectMatcher::class);

        $repository->create('/cu', '/moi-1');
        $matcher->match('/cu');

        $this->app->make(Cache::class)->forget('seo:redirects');
        Redirect::query()->where('source_path', '/cu')->update(['target' => '/moi-2']);

        // Octane is not installed in this suite — dispatched by the event's
        // own class name, exactly as the provider's listener is registered,
        // to prove the wiring itself works without requiring the package.
        Event::dispatch('Laravel\Octane\Events\RequestReceived');

        $this->assertSame('/moi-2', $matcher->match('/cu')?->target);
    }

    public function test_the_queue_job_processing_event_triggers_the_same_reset(): void
    {
        // A long-running `queue:work` process holds these singletons across
        // many jobs the identical way Octane holds them across many
        // requests — this is the far more common deployment of the two.
        config(['seo.redirects.cache_ttl' => 3600]);

        $repository = $this->app->make(RedirectRepository::class);
        /** @var RedirectMatcher $matcher */
        $matcher = $this->app->make(RedirectMatcher::class);

        $repository->create('/cu', '/moi-1');
        $matcher->match('/cu');

        $this->app->make(Cache::class)->forget('seo:redirects');
        Redirect::query()->where('source_path', '/cu')->update(['target' => '/moi-2']);

        // A real event object, not a bare string: Laravel's own
        // ContextServiceProvider already listens for this event too, and
        // its listener expects a real JobProcessing whose ->job->payload()
        // it can call.
        $job = new class {
            /** @return array<string, mixed> */
            public function payload(): array
            {
                return [];
            }
        };

        Event::dispatch(new JobProcessing('sync', $job));

        $this->assertSame('/moi-2', $matcher->match('/cu')?->target);
    }
}
