<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\NotFound;

use Duxbo\Seo\Ai\AiManager;
use Duxbo\Seo\Ai\Tools\Concerns\RanksCandidatesByTitleOverlap;
use Duxbo\Seo\Contracts\AiTool;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Enums\AiToolRisk;
use Duxbo\Seo\Seo;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Suggests a redirect target for a logged 404 from a shortlist of *real*
 * candidate pages built from their own resolved titles/slugs — the model
 * never sees an open invitation to invent a URL, only a bounded list plus a
 * schema that restricts its answer to exactly one of them (see
 * {@see \Duxbo\Seo\Ai\PromptLibrary::redirectTarget()}).
 */
final class SuggestRedirectTargetTool implements AiTool
{
    use RanksCandidatesByTitleOverlap;

    public function __construct(
        private readonly Seo $seo,
        private readonly AiManager $ai,
        private readonly Config $config,
    ) {
    }

    public function name(): string
    {
        return 'seo.not_found.suggest_redirect_target';
    }

    public function description(): string
    {
        return 'Suggest which existing page should replace a logged 404 path, chosen only from real '
            .'candidate URLs — never an invented one.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'The 404 log row id, from seo.not_found.list.'],
                'locale' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'description' => 'Candidates to consider. Defaults to 10.'],
            ],
            'required' => ['id'],
        ];
    }

    public function riskTier(): AiToolRisk
    {
        return AiToolRisk::Read;
    }

    public function execute(array $input, AiToolContext $context): ?array
    {
        $row = DB::table($this->table())->find((int) $input['id']);

        if ($row === null) {
            throw new NotFoundHttpException("No 404 log entry with id [{$input['id']}].");
        }

        $locale = isset($input['locale']) ? (string) $input['locale'] : $context->locale;
        $limit = min(30, max(1, (int) ($input['limit'] ?? 10)));
        $path = (string) $row->path;

        $candidates = $this->candidatesFor($path, $locale, $limit);

        if ($candidates === []) {
            return ['candidates' => [], 'message' => 'No candidate pages found to compare against.'];
        }

        return $this->ai->suggestRedirectTarget($path, $candidates, $locale) + ['candidates' => $candidates];
    }

    /**
     * @return list<array{url: string, title: string|null}>
     */
    private function candidatesFor(string $path, ?string $locale, int $limit): array
    {
        $terms = $this->termsOf($path);

        /** @var list<string> $exposed */
        $exposed = $this->config->get('seo.api.models', []);

        $scored = [];

        foreach ($exposed as $alias) {
            $class = Relation::getMorphedModel($alias) ?? $alias;

            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $scored = [...$scored, ...$this->rankByTitleOverlap($this->seo, $class, $terms, $locale)];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(
            static fn (array $candidate): array => ['url' => $candidate['url'], 'title' => $candidate['title']],
            array_slice($scored, 0, $limit),
        );
    }

    private function table(): string
    {
        return (string) $this->config->get('seo.not_found.table', 'seo_not_found');
    }
}
