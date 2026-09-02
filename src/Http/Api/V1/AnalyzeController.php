<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AnalyzeController extends ApiController
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
            'keyword' => ['nullable', 'string', 'max:191'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $report = $this->seo->analyze(
            $validated['content'],
            $validated['keyword'] ?? null,
            $validated['title'] ?? null,
            $validated['description'] ?? null,
            $validated['url'] ?? null,
            $validated['locale'] ?? null,
        );

        return $this->json($report->toArray());
    }
}
