<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers;

use Duxbo\Seo\Http\Concerns\ResolvesExposedModel;
use Duxbo\Seo\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Backs the Blade admin panel: renders the shell, and answers its fetch calls.
 *
 * Deliberately not the REST API under /api/seo/v1. That surface is meant for
 * an external SPA and typically carries a bearer token; a same-origin admin
 * page already has a session and a CSRF token, and routing its calls through
 * token auth would mean either standing up Sanctum just for this or teaching
 * the panel two authentication stories. Session and CSRF, under the `web`
 * middleware group, is what a page rendered by this same application already
 * has for free.
 */
final class PanelController
{
    use ResolvesExposedModel;

    public function __construct(private readonly Seo $seo)
    {
    }

    public function show(Request $request, string $type, string $id): View
    {
        $model = $this->resolveExposedModel($type, $id);
        $locale = $request->query('locale');

        return view('seo::panel.show', [
            'type' => $type,
            'id' => $id,
            'locale' => is_string($locale) ? $locale : null,
            'seoUrl' => $model->seoUrl(),
            'dataUrl' => route('seo.panel.data', ['type' => $type, 'id' => $id]),
            'analyzeUrl' => route('seo.panel.analyze', ['type' => $type, 'id' => $id]),
            'descriptionMin' => (int) config('seo.limits.description_min', 120),
            'descriptionMax' => (int) config('seo.limits.description_max', 158),
        ]);
    }

    public function data(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveExposedModel($type, $id);
        $locale = $request->query('locale');
        $locale = is_string($locale) ? $locale : null;

        return response()->json([
            'stored' => $this->seo->repository()->find($model, $locale)?->toArray(),
            'resolved' => $this->seo->for($model, $locale)->toArray(),
            'locales' => $this->seo->repository()->locales($model),
        ]);
    }

    public function update(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveExposedModel($type, $id);

        // The same explicit whitelist as the REST API: never $request->all(),
        // since this writes straight into stored metadata.
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'canonical' => ['nullable', 'string', 'max:2048'],
            'focusKeyword' => ['nullable', 'string', 'max:191'],
            'og' => ['nullable', 'array'],
            'og.image' => ['nullable', 'string', 'max:2048'],
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
            } else {
                $dotted[$key] = $value;
            }
        }

        $this->seo->save($model, $dotted, $locale);

        return response()->json(['resolved' => $this->seo->for($model, $locale)->toArray()]);
    }

    public function analyze(Request $request, string $type, string $id): JsonResponse
    {
        $this->resolveExposedModel($type, $id);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'keyword' => ['nullable', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $report = $this->seo->analyze(
            $validated['content'],
            $validated['keyword'] ?? null,
            $validated['title'] ?? null,
            $validated['description'] ?? null,
            null,
            $validated['locale'] ?? null,
        );

        return response()->json($report->toArray());
    }
}
