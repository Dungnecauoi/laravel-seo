<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;

/**
 * The key-file route is registered from config read at boot — separate from
 * IndexNowTest because it needs seo.indexnow.enabled and .key set before the
 * application boots, not flipped afterward with config().
 */
final class IndexNowKeyRouteTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.indexnow.enabled', true);
        $app['config']->set('seo.indexnow.key', 'test-key-123');
    }

    public function test_the_key_file_is_served_as_plain_text_at_the_configured_key(): void
    {
        $response = $this->get('/test-key-123.txt');

        $response->assertOk();
        $this->assertSame('test-key-123', $response->getContent());
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
    }

    public function test_a_different_path_is_not_swallowed_by_the_key_route(): void
    {
        $this->get('/some-other-key.txt')->assertNotFound();
    }
}
