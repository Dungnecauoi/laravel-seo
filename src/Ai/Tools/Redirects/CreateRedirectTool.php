<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Redirects;

use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\AiToolPreviewable;
use Duxbo\Seo\Contracts\RedirectMatcher;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;
use Duxbo\Seo\Redirects\RedirectGuard;
use Duxbo\Seo\Redirects\RedirectRepository;

/**
 * The AI-facing twin of {@see \Duxbo\Seo\Http\Api\V1\RedirectsController::store()}
 * — same repository, same safety checks. `preview()` runs those same checks
 * ahead of time (pattern safety, target safety, loop detection) so an
 * obviously bad rule fails on the propose call instead of wasting a
 * confirm round trip on something that was always going to be refused.
 */
final class CreateRedirectTool implements AiTool, AiToolPreviewable
{
    public function __construct(
        private readonly RedirectRepository $redirects,
        private readonly RedirectGuard $guard,
        private readonly RedirectMatcher $matcher,
    ) {
    }

    public function name(): string
    {
        return 'seo.redirects.create';
    }

    public function description(): string
    {
        return 'Create a redirect rule, or update an existing one by resubmitting the same source with a new target.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'source' => ['type' => 'string', 'description' => 'A path, or a regex pattern when type is "regex".'],
                'target' => ['type' => 'string', 'description' => 'Required unless status is 410 or 451.'],
                'type' => ['type' => 'string', 'enum' => ['exact', 'prefix', 'regex'], 'description' => 'Defaults to "exact".'],
                'status' => ['type' => 'integer', 'enum' => [301, 302, 307, 308, 410, 451], 'description' => 'Defaults to 301.'],
                'locale' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
            ],
            'required' => ['source'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Write;
    }

    public function preview(array $input, AiToolContext $context): string
    {
        [$source, $target, $status, $type] = $this->parse($input);

        $normalisedSource = $type === RedirectMatchType::Regex ? $source : $this->guard->normalise($source);

        $this->guard->assertPatternIsSafe($type, $normalisedSource);
        $this->guard->assertTargetIsSafe($target);

        if ($status->redirects()) {
            $this->guard->assertNoLoop(
                $normalisedSource,
                $target,
                fn (string $path): ?string => $this->matcher->match($path)?->target,
            );

            return sprintf('Would create a %d redirect from "%s" to "%s" (%s match).', $status->value, $source, $target, $type->value);
        }

        return sprintf('Would mark "%s" as %s (no target).', $source, $status->label());
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        [$source, $target, $status, $type, $locale, $notes] = $this->parse($input);

        $redirect = $this->redirects->create($source, $target, $status, $type, $locale, $notes);

        return ['id' => $redirect->getKey()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string|null, 2: RedirectType, 3: RedirectMatchType, 4: string|null, 5: string|null}
     */
    private function parse(array $input): array
    {
        $type = RedirectMatchType::tryFrom((string) ($input['type'] ?? 'exact')) ?? RedirectMatchType::Exact;
        $status = RedirectType::tryFrom((int) ($input['status'] ?? 301)) ?? RedirectType::MovedPermanently;
        $target = isset($input['target']) ? (string) $input['target'] : null;

        return [
            (string) $input['source'],
            $status->redirects() ? $target : null,
            $status,
            $type,
            isset($input['locale']) ? (string) $input['locale'] : null,
            isset($input['notes']) ? (string) $input['notes'] : null,
        ];
    }
}
