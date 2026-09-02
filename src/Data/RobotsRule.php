<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Enums\RobotsDirective;
use Duxbo\Seo\Exceptions\InvalidSeoData;
use Stringable;

/**
 * A robots directive together with its value, where it takes one.
 *
 * PHP enums cannot carry per-instance data, so `max-snippet:50` needs a wrapper
 * rather than an enum case. Keeping the wrapper narrow means the enum stays the
 * single source of truth for which keywords exist.
 */
final class RobotsRule implements Stringable
{
    public function __construct(
        public readonly RobotsDirective $directive,
        public readonly int|string|null $value = null,
    ) {
        if ($directive->requiresValue() && $value === null) {
            throw InvalidSeoData::directiveNeedsValue($directive->value);
        }

        if (! $directive->requiresValue() && $value !== null) {
            throw InvalidSeoData::directiveTakesNoValue($directive->value);
        }
    }

    public static function make(RobotsDirective $directive, int|string|null $value = null): self
    {
        return new self($directive, $value);
    }

    public static function noIndex(): self
    {
        return new self(RobotsDirective::NoIndex);
    }

    public static function noFollow(): self
    {
        return new self(RobotsDirective::NoFollow);
    }

    public static function maxSnippet(int $characters): self
    {
        return new self(RobotsDirective::MaxSnippet, $characters);
    }

    public static function maxImagePreview(string $size = 'large'): self
    {
        return new self(RobotsDirective::MaxImagePreview, $size);
    }

    public function contradicts(self $other): bool
    {
        return $this->directive->opposite() === $other->directive;
    }

    public function __toString(): string
    {
        return $this->value === null
            ? $this->directive->value
            : "{$this->directive->value}:{$this->value}";
    }
}
