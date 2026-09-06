<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class AiToolsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_every_registered_tool(): void
    {
        $this->artisan('seo:ai:tools')
            ->expectsOutputToContain('seo.dashboard.summary')
            ->expectsOutputToContain('read')
            ->expectsOutputToContain('seo.redirects.delete')
            ->expectsOutputToContain('destructive')
            ->assertSuccessful();
    }

    public function test_it_reports_when_nothing_is_registered(): void
    {
        config(['seo.ai.tools.enabled' => []]);

        $this->artisan('seo:ai:tools')
            ->expectsOutputToContain('No AI tools registered')
            ->assertSuccessful();
    }
}
