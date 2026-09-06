<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Ai\Tools\AiToolDispatcher;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Redirects\RedirectRepository;
use Duxbo\Seo\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * A human reviewing and confirming an AI's proposed action from the Blade
 * panel — the one write action on an otherwise read-only page. Confirming
 * here must go through the exact same Gate checks any other caller of
 * {@see AiToolDispatcher} does; there is no special panel-only bypass.
 */
final class AiToolCallsPanelConfirmTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('seo.panel.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewSeoPanel', static fn (mixed $user = null): bool => true);
    }

    public function test_a_pending_proposal_appears_on_the_index_page_with_a_confirm_button(): void
    {
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => true);

        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');
        $proposed = app(AiToolDispatcher::class)->call(
            'seo.redirects.delete',
            ['id' => $redirect->getKey()],
            new AiToolContext(),
        );

        $body = (string) $this->get('/seo/panel/ai-tool-calls')->getContent();

        $this->assertStringContainsString('Đề xuất đang chờ', $body);
        // The preview itself quotes the path/target in double quotes, which
        // the view (correctly) HTML-escapes to &quot; — compare on a
        // quote-free slice of it instead of the raw string.
        $this->assertStringContainsString('Would permanently delete redirect #'.$redirect->getKey(), $body);
        $this->assertStringContainsString("ai-tool-calls/{$proposed->proposalId}/confirm", $body);
    }

    public function test_confirming_a_pending_proposal_actually_applies_it(): void
    {
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => true);

        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');
        $proposed = app(AiToolDispatcher::class)->call(
            'seo.redirects.delete',
            ['id' => $redirect->getKey()],
            new AiToolContext(),
        );

        $this->post("/seo/panel/ai-tool-calls/{$proposed->proposalId}/confirm")
            ->assertRedirect()
            ->assertSessionHas('seo_status');

        $this->assertDatabaseMissing('seo_redirects', ['id' => $redirect->getKey()]);
        $this->assertDatabaseHas('seo_ai_tool_calls', ['proposal_id' => $proposed->proposalId, 'status' => 'applied']);
    }

    public function test_confirming_without_the_risk_tier_gate_is_refused_and_changes_nothing(): void
    {
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => true);

        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');
        $proposed = app(AiToolDispatcher::class)->call(
            'seo.redirects.delete',
            ['id' => $redirect->getKey()],
            new AiToolContext(),
        );

        // The gate is revoked *after* proposing — this must not be a bypass
        // of useSeoAiDestructive, the same Gate an API or MCP caller needs.
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => false);

        $this->post("/seo/panel/ai-tool-calls/{$proposed->proposalId}/confirm")
            ->assertRedirect()
            ->assertSessionHas('seo_ai_error');

        $this->assertDatabaseHas('seo_redirects', ['id' => $redirect->getKey()]);
    }

    public function test_confirming_an_unknown_proposal_id_shows_a_friendly_error_not_a_500(): void
    {
        $this->post('/seo/panel/ai-tool-calls/'.Str::uuid().'/confirm')
            ->assertRedirect()
            ->assertSessionHas('seo_ai_error');
    }

    public function test_an_already_applied_proposal_no_longer_shows_as_pending(): void
    {
        Gate::define('useSeoAiDestructive', static fn (mixed $user = null): bool => true);

        $redirect = app(RedirectRepository::class)->create('/cu', '/moi');
        $context = new AiToolContext();
        $proposed = app(AiToolDispatcher::class)->call('seo.redirects.delete', ['id' => $redirect->getKey()], $context);
        app(AiToolDispatcher::class)->call('seo.redirects.delete', [], $context, confirm: $proposed->proposalId);

        $body = (string) $this->get('/seo/panel/ai-tool-calls')->getContent();

        $this->assertStringNotContainsString('Đề xuất đang chờ', $body);
    }
}
