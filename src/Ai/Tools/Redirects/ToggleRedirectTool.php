<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Redirects;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Redirects\RedirectRepository;

/**
 * Enables or disables a redirect rule by id — an explicit desired state
 * rather than "flip whatever it currently is," since a caller enumerating
 * rules through {@see \Duxbo\Seo\Ai\Tools\Redirects\ListRedirectsTool} first
 * already knows the current state and can decide the target one directly.
 *
 * Re-enabling a rule that would now form a loop with other rules changed
 * while it sat disabled is refused by {@see Redirect}'s own `saving` guard
 * at execute() time — not duplicated here in `preview()`, since that guard
 * runs unconditionally on every save regardless of which caller triggered it.
 */
final class ToggleRedirectTool implements AiTool, AiToolPreviewable
{
    public function __construct(private readonly RedirectRepository $redirects)
    {
    }

    public function name(): string
    {
        return 'seo.redirects.toggle';
    }

    public function description(): string
    {
        return 'Enable or disable an existing redirect rule by id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'active' => ['type' => 'boolean'],
            ],
            'required' => ['id', 'active'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        $redirect = $this->find($input);
        $active = (bool) $input['active'];

        return sprintf(
            'Would set redirect #%d ("%s" \u{2192} "%s") to %s.',
            $redirect->getKey(),
            $redirect->source_path,
            $redirect->target ?? '(none)',
            $active ? 'active' : 'inactive',
        );
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $redirect = $this->find($input);

        $this->redirects->setActive((int) $redirect->getKey(), (bool) $input['active']);

        return ['id' => $redirect->getKey(), 'active' => (bool) $input['active']];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function find(array $input): Redirect
    {
        return Redirect::query()->findOrFail((int) $input['id']);
    }
}
