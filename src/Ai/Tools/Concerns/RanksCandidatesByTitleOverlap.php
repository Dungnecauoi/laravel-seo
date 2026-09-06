<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Tools\Concerns;

use Duxbo\Seo\Contracts\Seoable;
use Duxbo\Seo\Seo;
use Duxbo\Seo\Support\Text;

/**
 * A lexical heuristic for shortlisting real candidate URLs — token overlap
 * between a target string (a 404 path, an orphan's title) and each
 * candidate's own resolved title, nothing smarter than that. It exists only
 * to bound and rank what gets shown to the model; the schema's own `enum`
 * constraint (see {@see \Duxbo\Seo\Ai\PromptLibrary::redirectTarget()} and
 * {@see \Duxbo\Seo\Ai\PromptLibrary::internalLinkFix()}) is what actually
 * prevents a hallucinated URL, not the quality of this ranking.
 */
trait RanksCandidatesByTitleOverlap
{
    /**
     * @return list<string>
     */
    private function termsOf(string $text): array
    {
        $normalised = Text::stripDiacritics($text);
        $tokens = preg_split('/[^a-z0-9]+/', strtolower($normalised), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter(
            $tokens ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 3,
        )));
    }

    /**
     * @param  class-string  $class  A Seoable Eloquent model.
     * @param  list<string>  $terms
     * @return list<array{score: int, url: string, title: string|null}>
     */
    private function rankByTitleOverlap(
        Seo $seo,
        string $class,
        array $terms,
        ?string $locale,
        int $pool = 200,
        string|int|null $excludeKey = null,
    ): array {
        /** @var \Illuminate\Database\Eloquent\Model $probe */
        $probe = new $class();
        $scored = [];

        foreach ($class::query()->latest($probe->getKeyName())->limit($pool)->get() as $record) {
            if (! $record instanceof Seoable) {
                continue;
            }

            if ($excludeKey !== null && (string) $record->seoKey() === (string) $excludeKey) {
                continue;
            }

            $title = $seo->for($record, $locale)->title;
            $score = count(array_intersect($terms, $this->termsOf($title ?? '')));

            if ($score > 0) {
                $scored[] = ['score' => $score, 'url' => $record->seoUrl(), 'title' => $title];
            }
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $scored;
    }
}
