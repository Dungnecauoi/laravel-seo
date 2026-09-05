<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Data\Concerns\Copyable;

/**
 * The SEO payload for one page in one locale.
 *
 * This is what a repository stores, what the resolution pipeline fills in, and
 * what a formatter turns into meta tags. Every field is nullable: an unset
 * field means "not decided here", which is what lets a later pipeline stage
 * supply it without having to distinguish null from empty string.
 */
final class SeoData
{
    use Copyable;

    /**
     * @param  list<RobotsRule>  $robots
     * @param  list<string>  $secondaryKeywords
     * @param  array<string, mixed>  $extra  Room for user-defined fields; never read by the package.
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $canonical = null,
        public readonly array $robots = [],
        public readonly ?OpenGraphData $openGraph = null,
        public readonly ?TwitterData $twitter = null,
        public readonly ?string $focusKeyword = null,
        public readonly array $secondaryKeywords = [],
        public readonly ?int $score = null,
        public readonly array $extra = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Fill only the fields this object has not decided yet.
     *
     * The backbone of the fallback chain: a stage produces candidate values and
     * hands them here, and anything already set survives untouched.
     *
     * `openGraph` and `twitter` merge field by field rather than falling back
     * as a whole object — a record whose only og.* mapping is `og.title`
     * still produces a non-null `OpenGraphData` with every other field null,
     * and `$this->openGraph ?? $fallback->openGraph` would have picked that
     * object outright and discarded a later stage's `og.image` default along
     * with it, for every field the record never touched.
     */
    public function fillMissingFrom(self $fallback): self
    {
        return new self(
            title: $this->title ?? $fallback->title,
            description: $this->description ?? $fallback->description,
            canonical: $this->canonical ?? $fallback->canonical,
            robots: $this->robots !== [] ? $this->robots : $fallback->robots,
            openGraph: self::mergeNested($this->openGraph, $fallback->openGraph),
            twitter: self::mergeNested($this->twitter, $fallback->twitter),
            focusKeyword: $this->focusKeyword ?? $fallback->focusKeyword,
            secondaryKeywords: $this->secondaryKeywords !== [] ? $this->secondaryKeywords : $fallback->secondaryKeywords,
            score: $this->score ?? $fallback->score,
            extra: $this->extra + $fallback->extra,
        );
    }

    /**
     * @template T of OpenGraphData|TwitterData
     * @param  T|null  $current
     * @param  T|null  $fallback
     * @return T|null
     */
    private static function mergeNested(?object $current, ?object $fallback): ?object
    {
        if ($current === null) {
            return $fallback;
        }

        if ($fallback === null) {
            return $current;
        }

        /** @var T $current */
        /** @var T $fallback */
        return $current->fillMissingFrom($fallback);
    }

    public function hasRobotsDirective(string $directive): bool
    {
        foreach ($this->robots as $rule) {
            if ($rule->directive->value === $directive) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render the robots rules as a meta tag value, dropping contradictions.
     *
     * Later rules win: `[index, noindex]` renders as `noindex`, matching how a
     * per-page override is expected to beat a site default.
     */
    public function robotsLine(): ?string
    {
        if ($this->robots === []) {
            return null;
        }

        /** @var array<string, RobotsRule> $kept */
        $kept = [];

        foreach ($this->robots as $rule) {
            $opposite = $rule->directive->opposite();

            if ($opposite !== null) {
                unset($kept[$opposite->value]);
            }

            $kept[$rule->directive->value] = $rule;
        }

        return implode(', ', array_map(\strval(...), array_values($kept)));
    }

    public function isEmpty(): bool
    {
        return $this->title === null
            && $this->description === null
            && $this->canonical === null
            && $this->robots === []
            && $this->openGraph === null
            && $this->twitter === null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function constructorArgs(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'robots' => $this->robots,
            'openGraph' => $this->openGraph,
            'twitter' => $this->twitter,
            'focusKeyword' => $this->focusKeyword,
            'secondaryKeywords' => $this->secondaryKeywords,
            'score' => $this->score,
            'extra' => $this->extra,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'robots' => $this->robotsLine(),
            'openGraph' => $this->openGraph?->toArray(),
            'twitter' => $this->twitter?->toArray(),
            'focusKeyword' => $this->focusKeyword,
            'secondaryKeywords' => $this->secondaryKeywords,
            'score' => $this->score,
            'extra' => $this->extra,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
