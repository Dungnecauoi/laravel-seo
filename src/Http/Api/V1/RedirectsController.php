<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;
use Duxbo\Seo\Exceptions\UnsafeRedirect;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Redirects\RedirectRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The JSON twin of {@see \Duxbo\Seo\Http\Controllers\Panel\RedirectsController}
 * — same repository, same safety checks, JSON in and out instead of a form
 * post and a redirect response.
 */
final class RedirectsController extends ApiController
{
    public function __construct(private readonly RedirectRepository $redirects)
    {
    }

    public function index(): JsonResponse
    {
        $paginator = Redirect::query()->latest('id')->paginate(20);

        $data = array_map(static fn (Redirect $r): array => [
            'id' => $r->getKey(),
            'source' => $r->source_path,
            'target' => $r->target,
            'type' => $r->source_type->value,
            'status' => $r->status_code->value,
            'isActive' => $r->is_active,
            'locale' => $r->locale,
            'notes' => $r->notes,
            'hits' => $r->hits,
        ], $paginator->items());

        return $this->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Also how an existing rule is edited: create() upserts on the source
     * path, so resubmitting the same source with a new target updates it
     * rather than needing a separate edit endpoint.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:2048'],
            'target' => ['nullable', 'string', 'max:2048'],
            'type' => ['required', 'string', 'in:exact,prefix,regex'],
            'status' => ['required', 'integer', 'in:301,302,307,308,410,451'],
            'locale' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $status = RedirectType::from((int) $validated['status']);

        try {
            $redirect = $this->redirects->create(
                source: $validated['source'],
                target: $status->redirects() ? ($validated['target'] ?? null) : null,
                status: $status,
                type: RedirectMatchType::from($validated['type']),
                locale: $validated['locale'] ?? null,
                notes: $validated['notes'] ?? null,
            );
        } catch (UnsafeRedirect $e) {
            // The same guard that protects programmatic use of
            // RedirectRepository — surfaced here as an ordinary validation
            // error rather than a 500, since a client sending a bad rule is
            // an expected case, not a bug.
            throw ValidationException::withMessages(['source' => $e->getMessage()]);
        }

        return $this->json(['id' => $redirect->getKey()], 201);
    }

    public function toggle(int $id): JsonResponse
    {
        $redirect = Redirect::query()->findOrFail($id);

        $this->redirects->setActive($id, ! $redirect->is_active);

        return $this->json(['isActive' => ! $redirect->is_active]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->redirects->deleteById($id);

        return $this->json(['deleted' => true]);
    }
}
