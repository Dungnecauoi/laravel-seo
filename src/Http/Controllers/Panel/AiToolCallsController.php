<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class AiToolCallsController
{
    public function __invoke(): View
    {
        $table = (string) config('seo.ai.tools.table', 'seo_ai_tool_calls');

        return view('seo::panel.ai-tool-calls', [
            'paginator' => DB::table($table)->latest('id')->paginate(20)->withQueryString(),
        ]);
    }
}
