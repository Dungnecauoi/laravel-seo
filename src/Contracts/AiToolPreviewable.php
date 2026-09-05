<?php

declare(strict_types=1);

namespace Duxbo\Seo\Contracts;

use Duxbo\Seo\Data\AiToolContext;

/**
 * A cheap, side-effect-free description of what a Write or Destructive tool
 * would do, shown before it is confirmed. Optional: a tool that doesn't
 * implement this still gets the propose/confirm safety net from
 * {@see \Duxbo\Seo\Ai\Tools\AiToolDispatcher}, just with a generic preview
 * line instead of a specific one.
 */
interface AiToolPreviewable
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function preview(array $input, AiToolContext $context): string;
}
