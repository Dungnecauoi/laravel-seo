<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Analysis;

use Duxbo\Seo\Ai\SeoAiManager;
use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;

/**
 * Runs real content analysis, then asks the model to fix exactly what it
 * found — grounded in {@see \Duxbo\Seo\Data\AnalysisReport::problems()},
 * never a blind rewrite. A suggestion, not a mutation: pairs with
 * {@see \Duxbo\Seo\Ai\Tools\Meta\ApplyMetaTool} for a caller that wants to
 * write the result.
 */
final class SuggestContentFixesTool implements AiTool
{
    use ResolvesExposedModel;

    public function __construct(
        private readonly Seo $seo,
        private readonly SeoAiManager $ai,
    ) {
    }

    public function name(): string
    {
        return 'seo.analysis.suggest_fixes';
    }

    public function description(): string
    {
        return 'Run content analysis on a record and suggest a title/description that fix whatever it '
            .'actually found wrong, instead of writing generic meta from scratch.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string'],
                'id' => ['type' => 'string'],
                'content' => ['type' => 'string', 'description' => 'The page content (HTML or plain text) to analyze.'],
                'keyword' => ['type' => 'string'],
                'locale' => ['type' => 'string'],
            ],
            'required' => ['type', 'id', 'content'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $model = $this->resolveExposedModel((string) $input['type'], (string) $input['id']);
        $locale = isset($input['locale']) ? (string) $input['locale'] : $context->locale;
        $content = (string) $input['content'];

        $report = $this->seo->analyzeModel($model, $content, $locale);
        $problems = $report->problems();

        if ($problems === []) {
            return ['score' => $report->score, 'message' => 'No problems found by content analysis; nothing to fix.'];
        }

        $current = $this->seo->repository()->find($model, $locale);
        $keyword = isset($input['keyword']) ? (string) $input['keyword'] : $this->seo->for($model, $locale)->focusKeyword;

        $suggestion = $this->ai->suggestContentFixes(
            $content,
            $problems,
            $current,
            $keyword,
            (string) config('seo.site_name', ''),
            $locale,
        );

        return $suggestion + [
            'score' => $report->score,
            'addressedProblems' => array_map(static fn ($problem): string => $problem->id, $problems),
        ];
    }
}
