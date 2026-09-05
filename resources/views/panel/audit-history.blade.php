@extends('seo::panel.layout')

@section('title', 'Lịch sử audit')
@section('subtitle', 'Kết quả mỗi lần chạy php artisan seo:audit — theo dõi điểm SEO tăng giảm theo thời gian.')

@section('content')

    @if ($models->count() > 1)
        <div class="seo-card" style="padding:12px 16px">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('seo.panel.audit-history') }}"
                   class="seo-btn seo-btn-sm {{ $selectedModel === null ? 'seo-btn-primary' : 'seo-btn-secondary' }}">
                    Tất cả
                </a>
                @foreach ($models as $model)
                    <a href="{{ route('seo.panel.audit-history', ['model' => $model]) }}"
                       class="seo-btn seo-btn-sm {{ $selectedModel === $model ? 'seo-btn-primary' : 'seo-btn-secondary' }}">
                        {{ class_basename($model) }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($paginator->isEmpty())
        <div class="seo-empty">
            Chưa có lần audit nào. Chạy <code>php artisan seo:audit "App\Models\Post" --content=body</code> để bắt đầu.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>Số bản ghi</th>
                        <th>Điểm trung bình</th>
                        <th>Thấp nhất</th>
                        <th>Cao nhất</th>
                        <th>Chạy lúc</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $batch)
                        <tr>
                            <td class="seo-mono">{{ class_basename($batch->model) }}</td>
                            <td>{{ number_format($batch->total_records) }}</td>
                            <td>
                                @if ($batch->average_score !== null)
                                    <span class="seo-pill {{ $batch->average_score >= 80 ? 'is-ok' : ($batch->average_score >= 50 ? 'is-warn' : 'is-bad') }}">
                                        {{ number_format($batch->average_score, 1) }}
                                    </span>
                                @else
                                    <span class="seo-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $batch->min_score ?? '—' }}</td>
                            <td>{{ $batch->max_score ?? '—' }}</td>
                            <td class="seo-muted">{{ optional($batch->started_at)->format('d/m/Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($paginator->hasPages())
            <div style="display:flex;align-items:center;gap:8px;margin-top:16px">
                @if ($paginator->previousPageUrl())
                    <a class="seo-btn seo-btn-sm seo-btn-secondary" href="{{ $paginator->previousPageUrl() }}">← Trước</a>
                @endif
                <span class="seo-muted" style="font-size:12px">
                    Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}
                </span>
                @if ($paginator->nextPageUrl())
                    <a class="seo-btn seo-btn-sm seo-btn-secondary" href="{{ $paginator->nextPageUrl() }}">Sau →</a>
                @endif
            </div>
        @endif
    @endif

@endsection
