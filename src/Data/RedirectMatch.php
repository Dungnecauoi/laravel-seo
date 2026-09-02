<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;

/**
 * A redirect rule that claimed the incoming path.
 */
final class RedirectMatch
{
    public function __construct(
        public readonly int|string $ruleId,
        public readonly RedirectType $status,
        public readonly RedirectMatchType $matchedBy,
        public readonly ?string $target = null,
    ) {
    }

    /**
     * Whether the visitor is sent somewhere, as opposed to being told the
     * resource is gone (410) or blocked (451).
     */
    public function redirects(): bool
    {
        return $this->status->redirects() && $this->target !== null;
    }
}
