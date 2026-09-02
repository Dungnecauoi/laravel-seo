<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MetaController extends ApiController
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function show(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveModel($type, $id);
        $locale = $request->query('locale');

        return $this->json([
            'stored' => $this->seo->repository()->find($model, is_string($locale) ? $locale : null)?->toArray(),
            'resolved' => $this->seo->for($model, is_string($locale) ? $locale : null)->toArray(),
            'locales' => $this->seo->repository()->locales($model),
        ]);
    }

    public function update(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveModel($type, $id);

        // An explicit whitelist, never $request->all(): this endpoint writes
        // straight into stored metadata.
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'canonical' => ['nullable', 'string', 'max:2048'],
            'robots' => ['nullable', 'array'],
            'robots.*' => ['string', 'max:40'],
            'focusKeyword' => ['nullable', 'string', 'max:191'],
            'secondaryKeywords' => ['nullable', 'array'],
            'secondaryKeywords.*' => ['string', 'max:191'],
            'og' => ['nullable', 'array'],
            'twitter' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $locale = $validated['locale'] ?? null;
        unset($validated['locale']);

        $dotted = [];

        foreach ($validated as $key => $value) {
            if ($key === 'og' && is_array($value)) {
                foreach ($value as $k => $v) {
                    $dotted["og.{$k}"] = $v;
                }
            } elseif ($key === 'twitter' && is_array($value)) {
                foreach ($value as $k => $v) {
                    $dotted["twitter.{$k}"] = $v;
                }
            } else {
                $dotted[$key] = $value;
            }
        }

        $this->seo->save($model, $dotted, $locale);

        return $this->json(['resolved' => $this->seo->for($model, $locale)->toArray()]);
    }

    public function destroy(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveModel($type, $id);
        $locale = $request->query('locale');

        $this->seo->forget($model, is_string($locale) ? $locale : null);

        return $this->json(['deleted' => true]);
    }
}
