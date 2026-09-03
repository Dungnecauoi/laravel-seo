<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Illuminate\Http\JsonResponse;

/**
 * Shared plumbing for the v1 endpoints.
 *
 * The API version is deliberately independent of the package version. A SPA
 * deploys on its own schedule, and forcing it to upgrade in lockstep with the
 * backend is an operational nightmare — so v1 keeps its shape across package
 * majors, and only a genuine break earns a v2.
 */
abstract class ApiController
{
    use ResolvesExposedModel;

    protected function json(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, [
            'X-Seo-Contract' => '1',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
