<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Enums\RedirectMatchType;
use Duxbo\Seo\Enums\RedirectType;
use Duxbo\Seo\Exceptions\UnsafeRedirect;
use Duxbo\Seo\Redirects\Redirect;
use Duxbo\Seo\Redirects\RedirectRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RedirectsController
{
    public function __construct(private readonly RedirectRepository $redirects)
    {
    }

    public function index(): View
    {
        $paginator = Redirect::query()->latest('id')->paginate(20);

        return view('seo::panel.redirects', ['paginator' => $paginator]);
    }

    /**
     * Also how an existing rule is edited: create() upserts on the source
     * path, so resubmitting the same source with a new target updates it
     * rather than needing a separate edit endpoint.
     */
    public function store(Request $request): RedirectResponse
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
            $this->redirects->create(
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
            // error rather than a 500, since a panel user typing a bad rule
            // is an expected case, not a bug.
            throw ValidationException::withMessages(['source' => $e->getMessage()]);
        }

        return back()->with('seo_status', 'Đã lưu redirect.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $redirect = Redirect::query()->findOrFail($id);

        $this->redirects->setActive($id, ! $redirect->is_active);

        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->redirects->deleteById($id);

        return back()->with('seo_status', 'Đã xoá redirect.');
    }
}
