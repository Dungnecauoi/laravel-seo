<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\Fixtures\Post;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

/**
 * `ConsoleCommandTool::execute()` (every `seo.console.*` AI tool) calls
 * `Artisan::call()` from inside whatever request invoked it — a web
 * request through the REST API, the panel, or MCP over HTTP — not a real
 * `php artisan` process. `runningInConsole()` is false for that request.
 *
 * `ServiceProvider::commands()` only *registers* a deferred bootstrapper
 * (`Artisan::starting()`); if the call to `commands()` itself never runs
 * because it was gated behind `runningInConsole()`, the bootstrapper is
 * never queued, and the first `Artisan::call()` in that same request finds
 * no command registered at all. PHPUnit runs under the CLI SAPI, so
 * `runningInConsole()` is true throughout the rest of this suite — these
 * tests force it false to actually exercise the web-request case.
 */
final class ConsoleCommandsAvailableOutsideConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Must be set before the application boots — Application caches
        // runningInConsole()'s answer the first time anything asks.
        putenv('APP_RUNNING_IN_CONSOLE=false');
        $_ENV['APP_RUNNING_IN_CONSOLE'] = 'false';
        $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('APP_RUNNING_IN_CONSOLE');
        unset($_ENV['APP_RUNNING_IN_CONSOLE'], $_SERVER['APP_RUNNING_IN_CONSOLE']);

        parent::tearDown();
    }

    public function test_the_application_really_is_simulating_a_non_console_request(): void
    {
        // Guards the rest of this file: if this ever comes back true, every
        // other assertion here would pass for the wrong reason.
        $this->assertFalse($this->app->runningInConsole());
    }

    public function test_a_seo_command_can_be_called_via_artisan_from_a_non_console_request(): void
    {
        Post::query()->create(['name' => 'Bai viet', 'slug' => 'bai-viet']);

        $exitCode = Artisan::call('seo:internal-links', ['model' => Post::class]);

        $this->assertSame(0, $exitCode);
    }

    public function test_every_registered_seo_command_is_known_to_artisan(): void
    {
        $known = array_keys(Artisan::all());

        foreach ([
            'seo:sitemap', 'seo:prune-404', 'seo:duplicates', 'seo:hreflang',
            'seo:indexnow', 'seo:internal-links', 'seo:search-console:sync',
            'seo:ai:tools',
        ] as $command) {
            $this->assertContains($command, $known, "[{$command}] is not registered with Artisan.");
        }
    }
}
