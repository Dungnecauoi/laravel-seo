<?php

declare(strict_types=1);

namespace Duxbo\Seo\Ai\Drivers;

use Duxbo\Seo\Contracts\AiDriver;
use Duxbo\Seo\Data\AiRequest;
use Duxbo\Seo\Data\AiResponse;

/**
 * The default driver: does nothing, costs nothing.
 *
 * Installing a package must never start billing anyone, so AI is off until a
 * real driver is chosen. Also the driver tests run against, so a test suite
 * cannot accidentally call a paid API.
 */
final class NullDriver implements AiDriver
{
    /**
     * @param  array<string, mixed>  $canned  Returned as the response content.
     */
    public function __construct(private readonly array $canned = [])
    {
    }

    public function name(): string
    {
        return 'null';
    }

    public function complete(AiRequest $request): AiResponse
    {
        return new AiResponse(
            content: $this->canned,
            driver: $this->name(),
        );
    }
}
