<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;

/**
 * The panel is opted into, not exposed by installing the package.
 */
final class PanelDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.panel.enabled', false);
    }

    public function test_no_panel_routes_exist_when_it_is_disabled(): void
    {
        // A 404, not a 403: the routes are never registered at all.
        $this->get('/seo/panel/post/1')->assertNotFound();
    }
}
