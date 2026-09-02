<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Support\Compat;
use Duxbo\Seo\Tests\TestCase;

final class CompatTest extends TestCase
{
    public function test_it_reads_the_running_framework_major(): void
    {
        $this->assertSame(
            (int) explode('.', $this->app->version())[0],
            Compat::laravel(),
        );
    }

    public function test_the_running_framework_is_inside_the_supported_range(): void
    {
        Compat::assertSupported();

        $this->assertGreaterThanOrEqual(Compat::MIN_LARAVEL, Compat::laravel());
        $this->assertLessThanOrEqual(Compat::MAX_LARAVEL, Compat::laravel());
    }

    public function test_at_least_compares_against_the_running_major(): void
    {
        $this->assertTrue(Compat::atLeast(Compat::MIN_LARAVEL));
        $this->assertFalse(Compat::atLeast(Compat::MAX_LARAVEL + 1));
    }

    public function test_the_config_is_merged_so_the_package_works_unconfigured(): void
    {
        $this->assertNotNull(config('seo.site_name'));
        $this->assertSame(580, config('seo.limits.title_pixels'));
    }
}
