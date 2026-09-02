<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
    protected function json(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, [
            'X-Seo-Contract' => '1',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Resolve a morph alias and key to a record.
     *
     * Only types explicitly exposed in config can be addressed. Without that
     * allowlist, this endpoint would be a way to enumerate every model in the
     * application by guessing class names.
     */
    protected function resolveModel(string $type, int|string $id): Seoable
    {
        /** @var list<string> $exposed */
        $exposed = config('seo.api.models', []);

        if (! in_array($type, $exposed, true)) {
            throw new NotFoundHttpException("Type [{$type}] is not exposed by the SEO API.");
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw new NotFoundHttpException("Unknown type [{$type}].");
        }

        $record = $class::query()->find($id);

        if (! $record instanceof Seoable) {
            throw new NotFoundHttpException("No [{$type}] with key [{$id}].");
        }

        return $record;
    }
}
