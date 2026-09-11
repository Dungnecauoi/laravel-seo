<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * `SeoServiceProvider::registerMiddleware()` used to skip pushing
 * `HandleNotFound` onto the kernel entirely when both `seo.redirects.enabled`
 * and `seo.not_found.enabled` read false at the moment `boot()` ran. Under
 * ordinary PHP-FPM that's harmless — boot() re-runs every request — but
 * under Octane a worker's boot() runs once for its entire life. A worker
 * that boots with both flags off would never get the middleware at all,
 * and turning either flag back on later through the dynamic-settings API
 * (which only ever re-writes the live Config repository, never re-runs a
 * ServiceProvider's boot()) would silently do nothing until that worker
 * restarted.
 *
 * This simulates exactly that: both flags false when the app boots, then
 * flipped on afterward the same way SettingsRepository::resetForNewRequest()
 * does — a plain config() write, no reboot — proving the middleware was
 * already in the pipeline and just needed the flag to read true.
 */
final class HandleNotFoundMiddlewareAlwaysRegisteredTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.redirects.enabled', false);
        $app['config']->set('seo.not_found.enabled', false);
    }

    public function test_not_found_logging_enabled_at_runtime_works_without_a_reboot(): void
    {
        // Both flags were false when the app (and this middleware
        // registration) booted, above.
        config(['seo.not_found.enabled' => true]);

        $this->get('/khong-co-o-day');

        $this->assertDatabaseHas('seo_not_found', ['path' => '/khong-co-o-day']);
    }

    public function test_redirects_enabled_at_runtime_works_without_a_reboot(): void
    {
        DB::table('seo_redirects')->insert([
            'source_path' => '/cu',
            'source_hash' => md5('/cu'),
            'source_type' => 'exact',
            'target' => '/moi',
            'status_code' => 301,
            'is_active' => true,
        ]);

        config(['seo.redirects.enabled' => true]);

        $this->get('/cu')->assertRedirect('/moi');
    }
}
