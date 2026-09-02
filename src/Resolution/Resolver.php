<?php

declare(strict_types=1);

namespace Duxbo\Seo\Resolution;

use Duxbo\Seo\Contracts\ResolverStage;
use Duxbo\Seo\Data\SeoContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Pipeline\Pipeline;

/**
 * Runs the configured stages in order.
 *
 * The stage list is config, not code, which is the whole point: someone can
 * drop a stage, reorder two, or insert their own — "generate with AI when
 * empty", "read from an external CMS first" — without touching this package.
 */
final class Resolver
{
    /** @var list<class-string<ResolverStage>|ResolverStage> */
    private array $stages;

    /**
     * @param  list<class-string<ResolverStage>|ResolverStage>  $stages
     */
    public function __construct(
        private readonly Container $container,
        array $stages,
    ) {
        $this->stages = $stages;
    }

    public function resolve(SeoContext $context): SeoContext
    {
        /** @var SeoContext $resolved */
        $resolved = (new Pipeline($this->container))
            ->send($context)
            ->through($this->stages)
            ->thenReturn();

        return $resolved;
    }

    /**
     * @return list<class-string<ResolverStage>|ResolverStage>
     */
    public function stages(): array
    {
        return $this->stages;
    }

    /**
     * @param  class-string<ResolverStage>|ResolverStage  $stage
     */
    public function insertBefore(string $target, string|ResolverStage $stage): void
    {
        $position = $this->positionOf($target);

        array_splice($this->stages, $position === null ? 0 : $position, 0, [$stage]);
    }

    /**
     * @param  class-string<ResolverStage>|ResolverStage  $stage
     */
    public function insertAfter(string $target, string|ResolverStage $stage): void
    {
        $position = $this->positionOf($target);

        array_splice(
            $this->stages,
            $position === null ? count($this->stages) : $position + 1,
            0,
            [$stage],
        );
    }

    public function remove(string $target): void
    {
        $this->stages = array_values(array_filter(
            $this->stages,
            static fn (string|ResolverStage $stage): bool => (is_string($stage) ? $stage : $stage::class) !== $target,
        ));
    }

    private function positionOf(string $target): ?int
    {
        foreach ($this->stages as $index => $stage) {
            if ((is_string($stage) ? $stage : $stage::class) === $target) {
                return $index;
            }
        }

        return null;
    }
}
