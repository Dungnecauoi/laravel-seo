<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests;

use Duxbo\Seo\SeoServiceProvider;
use Duxbo\Seo\Support\Compat;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Treat the test environment as indexable, rather than forcing the app
        // environment to 'production' — Laravel guards destructive database
        // commands there, and RefreshDatabase would never build the schema.
        // This also exercises the real knob: which environments are indexable
        // is configuration, not a hard-coded string comparison.
        $app['config']->set('seo.indexable_environments', ['testing']);

        $app['config']->set('seo.site_name', 'Trang Của Tôi');

        // Only exercised by the panel's web-middleware routes, which encrypt
        // the session cookie — but harmless, and simpler, to set unconditionally.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->string('cover_url')->nullable();
            $table->timestamps();
        });
    }
}
