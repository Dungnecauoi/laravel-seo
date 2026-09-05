<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Settings\SettingsRepository;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

final class PanelDynamicSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.panel.enabled', true);
        $app['config']->set('seo.settings.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Relation::enforceMorphMap(['post' => \Duxbo\Seo\Tests\Fixtures\Post::class]);
        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
    }

    protected function tearDown(): void
    {
        Relation::morphMap([], false);
        Relation::requireMorphMap(false);

        parent::tearDown();
    }

    public function test_the_form_is_hidden_when_dynamic_settings_are_disabled(): void
    {
        config(['seo.settings.enabled' => false]);

        $this->get('/seo/panel/settings')
            ->assertOk()
            ->assertSee('Cấu hình động đang tắt')
            ->assertDontSee('name="enabled"', false);
    }

    public function test_the_form_shows_current_values_when_enabled(): void
    {
        $this->get('/seo/panel/settings')
            ->assertOk()
            ->assertSee('Chỉnh cấu hình')
            ->assertSee('name="verification__google"', false);
    }

    public function test_saving_a_text_field_creates_an_override_and_reflects_downstream(): void
    {
        $this->from('/seo/panel/settings')
            ->post('/seo/panel/settings', [
                '_method' => 'PUT',
                'verification__google' => 'abc123',
            ] + $this->untouchedFields())
            ->assertRedirect('/seo/panel/settings');

        $this->assertSame('abc123', config('seo.verification.google'));
        $this->assertDatabaseHas('seo_settings', ['key' => 'verification.google']);
    }

    public function test_a_checkbox_left_unchecked_saves_false_not_leaving_the_key_untouched(): void
    {
        app($this->settings())->set('robots.block_ai_crawlers', true);

        $this->post('/seo/panel/settings', [
            '_method' => 'PUT',
        ] + $this->untouchedFields())->assertRedirect();

        $this->assertFalse(config('seo.robots.block_ai_crawlers'));
    }

    public function test_clearing_a_text_field_reverts_to_the_config_file_default(): void
    {
        app($this->settings())->set('verification.google', 'was-set');

        $this->post('/seo/panel/settings', [
            '_method' => 'PUT',
            'verification__google' => '',
        ] + $this->untouchedFields())->assertRedirect();

        $this->assertFalse(app($this->settings())->has('verification.google'));
    }

    public function test_a_blank_secret_field_leaves_the_stored_secret_untouched(): void
    {
        app($this->settings())->set('search_console.refresh_token', 'original-token');

        $this->post('/seo/panel/settings', [
            '_method' => 'PUT',
            'search_console__refresh_token' => '',
        ] + $this->untouchedFields())->assertRedirect();

        $this->assertSame('original-token', app($this->settings())->get('search_console.refresh_token'));
    }

    public function test_a_non_blank_secret_field_replaces_the_stored_secret(): void
    {
        $this->post('/seo/panel/settings', [
            '_method' => 'PUT',
            'search_console__refresh_token' => 'new-token',
        ] + $this->untouchedFields())->assertRedirect();

        $this->assertSame('new-token', app($this->settings())->get('search_console.refresh_token'));
    }

    public function test_a_secret_value_never_appears_in_the_rendered_form(): void
    {
        app($this->settings())->set('search_console.refresh_token', 'super-secret-value');

        $body = (string) $this->get('/seo/panel/settings')->getContent();

        $this->assertStringNotContainsString('super-secret-value', $body);
        $this->assertStringContainsString('Đã đặt', $body);
    }

    public function test_the_samesas_textarea_round_trips_as_a_list(): void
    {
        $this->post('/seo/panel/settings', [
            '_method' => 'PUT',
            'schema__organization__sameAs' => "https://facebook.com/x\nhttps://youtube.com/x",
        ] + $this->untouchedFields())->assertRedirect();

        $this->assertSame(
            ['https://facebook.com/x', 'https://youtube.com/x'],
            app($this->settings())->get('schema.organization.sameAs'),
        );
    }

    public function test_dynamic_settings_routes_404_when_disabled(): void
    {
        config(['seo.settings.enabled' => false]);

        $this->post('/seo/panel/settings', ['_method' => 'PUT'])->assertNotFound();
    }

    /**
     * Every checkbox key must be posted as absent (unchecked) rather than
     * omitted from the request entirely being confused with "field not in
     * this form" — the controller writes every allowlisted key on every
     * submit, so each test above only needs to name the field it cares
     * about, not reconstruct a full form payload.
     *
     * @return array<string, string>
     */
    private function untouchedFields(): array
    {
        return [];
    }

    private function settings(): string
    {
        return SettingsRepository::class;
    }
}
