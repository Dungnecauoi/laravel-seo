<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests;

use Duxbo\Seo\SeoServiceProvider;
use Duxbo\Seo\Support\Compat;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function tearDown(): void
    {
        Compat::flush();

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SeoServiceProvider::class];
    }
}
