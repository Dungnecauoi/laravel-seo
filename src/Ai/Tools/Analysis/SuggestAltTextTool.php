<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Analysis;

use Duxbo\Seo\Ai\SeoAiManager;
use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Contracts\ContentExtractor;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Data\ImageRef;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;

/**
 * Suggests alt text for images missing it in a record's content.
 *
 * Grounded in the page's own text and each image's file name — never in
 * what the image actually shows, since nothing in this package's AI
 * pipeline is multi-modal. A caller should present this as a starting
 * point to review, not a finished description; unlike {@see
 * \Duxbo\Seo\Ai\Tools\Meta\ApplyMetaTool}, there is no apply counterpart
 * here, the same reason {@see
 * \Duxbo\Seo\Ai\Tools\InternalLinks\SuggestInternalLinkFixesTool} has
 * none: alt text lives inside a model's own body content, and this
 * package has no write path into that.
 */
final class SuggestAltTextTool implements AiTool
{
    use ResolvesExposedModel;

    public function __construct(
        private readonly Seo $seo,
        private readonly SeoAiManager $ai,
        private readonly ContentExtractor $extractor,
    ) {
    }

    public function name(): string
    {
        return 'seo.analysis.suggest_alt_text';
    }

    public function description(): string
    {
        return 'Suggest alt text for images missing it in a record\'s content, inferred from the page\'s own '
            .'text and each image\'s file name — not real vision, no image is actually analyzed.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string'],
                'id' => ['type' => 'string'],
                'content' => ['type' => 'string', 'description' => 'The page content (HTML) to scan for images.'],
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

        $missing = $this->extractor->extract($content)->imagesMissingAlt();

        if ($missing === []) {
            return ['message' => 'Every image already has alt text; nothing to suggest.'];
        }

        $imageUrls = array_values(array_unique(array_map(
            static fn (ImageRef $image): string => $image->src,
            $missing,
        )));

        $keyword = $this->seo->for($model, $locale)->focusKeyword;

        $suggestion = $this->ai->suggestAltText($content, $imageUrls, $keyword, $locale);

        return $suggestion + ['missingCount' => count($missing)];
    }
}
