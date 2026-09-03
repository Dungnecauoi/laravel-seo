<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Concerns;

use Duxbo\Seo\Contracts\Seoable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves a morph alias and key to a record, through an allowlist.
 *
 * Shared by the REST API and the Blade panel — both let a caller name a model
 * by string, and without the same allowlist in both places, one of the two
 * surfaces would let a caller enumerate every model in the application by
 * guessing class names while the other stayed locked down.
 */
trait ResolvesExposedModel
{
    /**
     * @return list<string>
     */
    protected function exposedModelTypes(): array
    {
        /** @var list<string> $exposed */
        $exposed = config('seo.api.models', []);

        return $exposed;
    }

    protected function resolveExposedModel(string $type, int|string $id): Seoable
    {
        if (! in_array($type, $this->exposedModelTypes(), true)) {
            throw new NotFoundHttpException("Type [{$type}] is not exposed to the SEO panel or API.");
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
