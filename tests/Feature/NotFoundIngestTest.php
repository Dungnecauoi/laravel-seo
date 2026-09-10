<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class NotFoundIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The route is only registered when this is set at boot — see
        // routes/seo.php — so it must be configured before the app boots,
        // not inside an individual test.
        $app['config']->set('seo.not_found.ingest_token', 'test-token');
    }

    public function test_a_valid_token_and_path_are_accepted_and_logged(): void
    {
        $this->postJson('/api/seo/v1/not-found', ['path' => '/tu-nextjs'], [
            'X-Seo-Ingest-Token' => 'test-token',
        ])->assertStatus(202)->assertJson(['accepted' => true]);

        $this->assertDatabaseHas('seo_not_found', ['path' => '/tu-nextjs']);
    }

    public function test_referrer_and_user_agent_are_optional_and_stored_when_given(): void
    {
        $this->postJson('/api/seo/v1/not-found', [
            'path' => '/tu-nextjs',
            'referrer' => 'https://google.com',
            'userAgent' => 'Mozilla/5.0',
        ], [
            'X-Seo-Ingest-Token' => 'test-token',
        ])->assertStatus(202);

        $this->assertDatabaseHas('seo_not_found', [
            'path' => '/tu-nextjs',
            'referrer' => 'https://google.com',
            'user_agent' => 'Mozilla/5.0',
        ]);
    }

    public function test_a_wrong_token_is_refused_and_nothing_is_written(): void
    {
        $this->postJson('/api/seo/v1/not-found', ['path' => '/tu-nextjs'], [
            'X-Seo-Ingest-Token' => 'not-the-right-token',
        ])->assertStatus(401);

        $this->assertDatabaseMissing('seo_not_found', ['path' => '/tu-nextjs']);
    }

    public function test_a_missing_token_header_is_refused(): void
    {
        $this->postJson('/api/seo/v1/not-found', ['path' => '/tu-nextjs'])
            ->assertStatus(401);
    }

    public function test_a_missing_path_is_a_clean_422_not_a_500(): void
    {
        $response = $this->postJson('/api/seo/v1/not-found', [], [
            'X-Seo-Ingest-Token' => 'test-token',
        ]);

        // A clean, directly-built JSON error — not a session-flash
        // redirect, which a headless caller with no session could never
        // follow anyway.
        $response->assertStatus(422)->assertJsonValidationErrors(['path']);
    }

    public function test_a_path_not_starting_with_a_slash_is_rejected(): void
    {
        $this->postJson('/api/seo/v1/not-found', ['path' => 'khong-co-dau-cham'], [
            'X-Seo-Ingest-Token' => 'test-token',
        ])->assertStatus(422);
    }

    public function test_the_route_works_even_when_the_admin_api_is_disabled(): void
    {
        config(['seo.api.enabled' => false]);

        $this->postJson('/api/seo/v1/not-found', ['path' => '/tu-nextjs'], [
            'X-Seo-Ingest-Token' => 'test-token',
        ])->assertStatus(202);
    }
}
