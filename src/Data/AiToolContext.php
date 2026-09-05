<?php

declare(strict_types=1);

namespace Duxbo\Seo\Data;

/**
 * Who's calling a tool, and how — passed to every {@see \Duxbo\Seo\Contracts\AiTool}
 * so it can behave the same regardless of whether the call came over the
 * REST manifest, the MCP endpoint, or a plain in-process PHP call.
 */
final class AiToolContext
{
    public function __construct(
        public readonly mixed $user = null,
        public readonly ?string $scope = null,
        public readonly ?string $locale = null,
        public readonly string $transport = 'in_process',
    ) {
    }
}
