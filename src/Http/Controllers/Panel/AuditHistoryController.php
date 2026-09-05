<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers\Panel;

use Duxbo\Seo\Audit\AuditBatch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class AuditHistoryController
{
    public function __invoke(Request $request): View
    {
        $model = $request->query('model');

        $query = AuditBatch::query()->latest('id');

        if (is_string($model) && $model !== '') {
            $query->where('model', $model);
        }

        return view('seo::panel.audit-history', [
            'paginator' => $query->paginate(20)->withQueryString(),
            'models' => AuditBatch::query()->select('model')->distinct()->pluck('model'),
            'selectedModel' => $model,
        ]);
    }
}
