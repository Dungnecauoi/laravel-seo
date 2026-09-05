<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

final class DynamicSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.api.enabled', true);
        $app['config']->set('seo.settings.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
    }

    public function test_denied_by_default_without_the_gate(): void
    {
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => false);

        $this->getJson('/api/seo/v1/dynamic-settings')->assertForbidden();
    }

    public function test_index_lists_every_allowlisted_key_with_its_current_value(): void
    {
        // Every allowlisted key contains a literal dot, which conflicts with
        // assertJsonPath's own dot-notation traversal — read the array
        // directly instead of trying to escape it into a path string.
        $settings = $this->getJson('/api/seo/v1/dynamic-settings')->assertOk()->json('settings');

        $this->assertFalse($settings['verification.google']['overridden']);
    }

    public function test_update_saves_a_batch_and_reflects_it_on_the_next_index_call(): void
    {
        $this->putJson('/api/seo/v1/dynamic-settings', [
            'settings' => [
                'verification.google' => 'abc123',
                'robots.block_ai_crawlers' => true,
            ],
        ])->assertOk()->assertJsonPath('saved', ['verification.google', 'robots.block_ai_crawlers']);

        $settings = $this->getJson('/api/seo/v1/dynamic-settings')->assertOk()->json('settings');

        $this->assertSame('abc123', $settings['verification.google']['value']);
        $this->assertTrue($settings['verification.google']['overridden']);
        $this->assertTrue($settings['robots.block_ai_crawlers']['value']);
    }

    public function test_update_rejects_the_whole_batch_when_one_key_is_not_allowlisted(): void
    {
        $this->putJson('/api/seo/v1/dynamic-settings', [
            'settings' => [
                'verification.google' => 'abc123',
                'storage.connection' => 'mysql',
            ],
        ])->assertStatus(422);

        // Neither key was saved — not even the valid one alongside it.
        $this->assertDatabaseMissing('seo_settings', ['key' => 'verification.google']);
    }

    public function test_destroy_clears_a_stored_override(): void
    {
        $this->putJson('/api/seo/v1/dynamic-settings', [
            'settings' => ['verification.google' => 'abc123'],
        ])->assertOk();

        $this->deleteJson('/api/seo/v1/dynamic-settings/verification.google')
            ->assertOk()
            ->assertJsonPath('cleared', 'verification.google');

        $this->assertDatabaseMissing('seo_settings', ['key' => 'verification.google']);
    }

    public function test_destroy_of_an_unknown_key_is_a_422_not_a_500(): void
    {
        $this->deleteJson('/api/seo/v1/dynamic-settings/storage.connection')
            ->assertStatus(422);
    }

    public function test_a_secret_key_never_echoes_its_value_back(): void
    {
        $this->putJson('/api/seo/v1/dynamic-settings', [
            'settings' => ['search_console.refresh_token' => 'super-secret-token'],
        ])->assertOk();

        $body = (string) $this->getJson('/api/seo/v1/dynamic-settings')->assertOk()->getContent();
        $settings = $this->getJson('/api/seo/v1/dynamic-settings')->json('settings');

        $this->assertStringNotContainsString('super-secret-token', $body);
        $this->assertTrue($settings['search_console.refresh_token']['secret']);
        $this->assertTrue($settings['search_console.refresh_token']['is_set']);
        $this->assertArrayNotHasKey('value', $settings['search_console.refresh_token']);
        $this->assertArrayNotHasKey('default', $settings['search_console.refresh_token']);
    }

    public function test_a_secret_key_configured_only_in_the_config_file_still_reports_is_set_without_leaking_it(): void
    {
        config(['seo.search_console.client_secret' => 'file-configured-secret']);

        $body = (string) $this->getJson('/api/seo/v1/dynamic-settings')->assertOk()->getContent();
        $settings = $this->getJson('/api/seo/v1/dynamic-settings')->json('settings');

        $this->assertStringNotContainsString('file-configured-secret', $body);
        $this->assertTrue($settings['search_console.client_secret']['is_set']);
        $this->assertFalse($settings['search_console.client_secret']['overridden']);
    }

    public function test_a_non_secret_key_still_reports_its_value_normally(): void
    {
        $settings = $this->getJson('/api/seo/v1/dynamic-settings')->assertOk()->json('settings');

        $this->assertFalse($settings['search_console.client_id']['secret']);
        $this->assertArrayHasKey('value', $settings['search_console.client_id']);
    }
}
