<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Http\Concerns\WarnsAboutDuplicates;
use Duxbo\Seo\Seo;
use Duxbo\Seo\Support\SameOriginUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MetaController extends ApiController
{
    use WarnsAboutDuplicates;

    public function __construct(
        private readonly Seo $seo,
        private readonly SameOriginUrls $sameOrigin,
    ) {
    }

    public function show(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveExposedModel($type, $id);
        $locale = $request->query('locale');

        return $this->json([
            'stored' => $this->seo->repository()->find($model, is_string($locale) ? $locale : null)?->toArray(),
            'resolved' => $this->seo->for($model, is_string($locale) ? $locale : null)->toArray(),
            'locales' => $this->seo->repository()->locales($model),
        ]);
    }

    public function update(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveExposedModel($type, $id);

        // An explicit whitelist, never $request->all(): this endpoint writes
        // straight into stored metadata.
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'canonical' => ['nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                // A canonical pointed off-site tells search engines this
                // page's real home is elsewhere, and can pull it out of the
                // index entirely — the same class of mistake as an open
                // redirect, just quieter.
                if (is_string($value) && $value !== '' && ! $this->sameOrigin->isAllowed($value)) {
                    $fail('The canonical URL must be a path, or a URL on an allowed host.');
                }
            }],
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

        return $this->json([
            'resolved' => $this->seo->for($model, $locale)->toArray(),
            'warnings' => $this->duplicateWarnings($this->seo, $model, $dotted, $locale),
        ]);
    }

    public function destroy(Request $request, string $type, string $id): JsonResponse
    {
        $model = $this->resolveExposedModel($type, $id);
        $locale = $request->query('locale');

        $this->seo->forget($model, is_string($locale) ? $locale : null);

        return $this->json(['deleted' => true]);
    }
}
