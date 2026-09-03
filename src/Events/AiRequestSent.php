<?php

declare(strict_types=1);

namespace Duxbo\Seo\Events;

use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;

/**
 * Fired after every completion — for cost tracking and audit.
 */
final class AiRequestSent
{
    public function __construct(
        public readonly AiRequest $request,
        public readonly AiResponse $response,
        public readonly ?string $purpose = null,
    ) {
    }
}
