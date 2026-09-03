<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Enums\RedirectType;
use Duxbo\Seo\Exceptions\UnsafeRedirect;
use Duxbo\Seo\NotFound\NotFoundLogger;
use Duxbo\Seo\Redirects\RedirectRepository;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reads the 404 log. Every field here was supplied by whoever made the
 * request — path, referrer, user agent alike — so the view escapes all of it
 * on the way out; this is the one page in the panel rendering attacker-
 * supplied text.
 */
final class NotFoundMonitorController
{
    public function __construct(private readonly RedirectRepository $redirects)
    {
    }

    public function index(): View
    {
        $rows = DB::table($this->table())
            ->orderByDesc('hits')
            ->paginate(30);

        return view('seo::panel.not-found', ['paginator' => $rows]);
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table($this->table())->where('id', $id)->delete();

        return back();
    }

    public function prune(Request $request): RedirectResponse
    {
        $days = (int) $request->input('days', 90);
        $deleted = app(NotFoundLogger::class)->prune(max(1, $days));

        return back()->with('seo_status', "Đã xoá {$deleted} dòng cũ hơn {$days} ngày.");
    }

    /**
     * The quick "turn this 404 into a redirect" action — the whole reason a
     * 404 monitor is more useful next to a redirect manager than alone.
     */
    public function redirect(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'target' => ['required', 'string', 'max:2048'],
        ]);

        $row = DB::table($this->table())->find($id);

        if ($row === null) {
            return back();
        }

        try {
            $this->redirects->create(source: (string) $row->path, target: $validated['target'], status: RedirectType::MovedPermanently);
        } catch (UnsafeRedirect $e) {
            throw ValidationException::withMessages(['target' => $e->getMessage()]);
        }

        DB::table($this->table())->where('id', $id)->delete();

        return redirect()->route('seo.panel.redirects.index')->with('seo_status', 'Đã tạo redirect từ mục 404.');
    }

    private function table(): string
    {
        return (string) config('seo.not_found.table', 'seo_not_found');
    }
}
