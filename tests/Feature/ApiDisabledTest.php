<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;

/**
 * The API surface is opted into, not exposed by installing the package.
 */
final class ApiDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.api.enabled', false);
    }

    public function test_no_api_routes_exist_when_it_is_disabled(): void
    {
        // A 404, not a 403: the routes are never registered at all.
        $this->getJson('/api/seo/v1/resolve?url=/x')->assertNotFound();
        $this->postJson('/api/seo/v1/analyze', ['content' => 'x'])->assertNotFound();
    }
}
