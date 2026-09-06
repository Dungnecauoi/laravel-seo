<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\InternalLinks;

use Duxbo\Seo\Ai\AiManager;
use Duxbo\Seo\Ai\Tools\Concerns\RanksCandidatesByTitleOverlap;
use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;

/**
 * Suggests which same-type pages should link to an orphaned one, and with
 * what anchor text — propose-only, since this package has no write path
 * into a model's own body content to actually insert a link. Candidates are
 * real sibling records ranked by title overlap with the orphan (see
 * {@see RanksCandidatesByTitleOverlap}), and the schema restricts the
 * model's answer to exactly those URLs.
 */
final class SuggestInternalLinkFixesTool implements AiTool
{
    use ResolvesExposedModel;
    use RanksCandidatesByTitleOverlap;

    public function __construct(
        private readonly Seo $seo,
        private readonly AiManager $ai,
    ) {
    }

    public function name(): string
    {
        return 'seo.internal_links.suggest_fixes';
    }

    public function description(): string
    {
        return 'Suggest which existing pages of the same type should link to an orphaned record, with '
            .'suggested anchor text — a proposal only, this package cannot edit body content itself.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string'],
                'id' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'description' => 'Candidates to consider. Defaults to 8.'],
            ],
            'required' => ['type', 'id'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $orphan = $this->resolveExposedModel((string) $input['type'], (string) $input['id']);
        $locale = isset($input['locale']) ? (string) $input['locale'] : $context->locale;
        $limit = min(20, max(1, (int) ($input['limit'] ?? 8)));

        $orphanTitle = $this->seo->for($orphan, $locale)->title ?? $orphan->seoUrl();
        $terms = $this->termsOf($orphanTitle);

        $ranked = $this->rankByTitleOverlap(
            $this->seo,
            $orphan::class,
            $terms,
            $locale,
            excludeKey: $orphan->seoKey(),
        );

        $candidates = array_map(
            static fn (array $candidate): array => ['url' => $candidate['url'], 'title' => $candidate['title']],
            array_slice($ranked, 0, $limit),
        );

        if ($candidates === []) {
            return ['suggestions' => [], 'message' => 'No topically-related candidate pages found.'];
        }

        return $this->ai->suggestInternalLinkFixes($orphan->seoUrl(), $orphanTitle, $candidates, $locale)
            + ['candidates' => $candidates];
    }
}
