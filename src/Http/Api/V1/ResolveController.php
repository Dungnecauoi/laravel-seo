<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Data\SeoContext;
use Duxbo\Seo\Exceptions\UnknownFormatter;
use Duxbo\Seo\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Metadata for a URL, in whichever shape the caller's framework expects.
 *
 * `?format=next` returns the exact object Next.js `generateMetadata()` wants,
 * `?format=nuxt` the payload `useHead()` takes. The front end maps nothing.
 */
final class ResolveController extends ApiController
{
    public function __construct(private readonly Seo $seo)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:10'],
            'format' => ['nullable', 'string', 'max:32'],
        ]);

        $context = $this->seo->resolve(SeoContext::forUrl(
            $validated['url'],
            $validated['locale'] ?? null,
        ));

        $format = $validated['format'] ?? 'array';

        try {
            return $this->json($this->seo->format($format, $context));
        } catch (UnknownFormatter $e) {
            return $this->json(['message' => $e->getMessage()], 422);
        }
    }
}
