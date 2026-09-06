<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Ai\Tools\AiToolDispatcher;
use Duxbo\Seo\Data\AiToolContext;
use Duxbo\Seo\Exceptions\AiToolProposalExpired;
use Duxbo\Seo\Exceptions\AiToolUnauthorized;
use Duxbo\Seo\Exceptions\SeoException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reads {@see AiToolDispatcher}'s own log, and — the one write action on
 * this otherwise read-only page — lets a signed-in panel user confirm a
 * pending AI proposal themselves: a human reviewing and approving an AI's
 * proposed action before it runs, the same "human in the loop" MCP's own
 * spec asks every client-side integration to provide.
 *
 * Confirming here goes through the exact same {@see AiToolDispatcher::call()}
 * every other caller does, built from *this* request's own user — it is
 * not a bypass of `useSeoAiWrites`/`useSeoAiDestructive`. If those Gates
 * still deny this user, confirming from the panel is refused exactly like
 * confirming from an API call would be; an application that wants panel
 * users to be able to approve AI actions has to grant them those abilities,
 * the same as it would for any other caller.
 */
final class AiToolCallsController
{
    public function index(): View
    {
        $table = $this->table();
        $ttl = (int) config('seo.ai.tools.proposal_ttl', 900);

        $appliedProposalIds = DB::table($table)
            ->where('status', 'applied')
            ->whereNotNull('proposal_id')
            ->pluck('proposal_id');

        $pending = DB::table($table)
            ->where('status', 'proposed')
            ->whereNotNull('proposal_id')
            ->whereNotIn('proposal_id', $appliedProposalIds)
            ->where('created_at', '>=', Carbon::now()->subSeconds($ttl))
            ->latest('id')
            ->get();

        return view('seo::panel.ai-tool-calls', [
            'pending' => $pending,
            'paginator' => DB::table($table)->latest('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function confirm(Request $request, string $proposalId, AiToolDispatcher $dispatcher): RedirectResponse
    {
        $row = DB::table($this->table())
            ->where('proposal_id', $proposalId)
            ->where('status', 'proposed')
            ->first();

        if ($row === null) {
            return back()->with('seo_ai_error', 'Không tìm thấy đề xuất này — có thể đã được xử lý hoặc hết hạn.');
        }

        $context = new AiToolContext(user: $request->user(), transport: 'panel');

        try {
            $dispatcher->call((string) $row->tool, [], $context, confirm: $proposalId);
        } catch (AiToolProposalExpired) {
            return back()->with('seo_ai_error', 'Đề xuất đã hết hạn — yêu cầu AI đề xuất lại.');
        } catch (AiToolUnauthorized) {
            return back()->with('seo_ai_error', 'Bạn không có quyền xác nhận hành động này (thiếu Gate useSeoAiWrites/useSeoAiDestructive).');
        } catch (SeoException $e) {
            return back()->with('seo_ai_error', $e->getMessage());
        }

        return back()->with('seo_status', "Đã áp dụng đề xuất cho tool [{$row->tool}].");
    }

    private function table(): string
    {
        return (string) config('seo.ai.tools.table', 'seo_ai_tool_calls');
    }
}
