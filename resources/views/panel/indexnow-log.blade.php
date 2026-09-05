@extends('seo::panel.layout')

@section('title', 'IndexNow')
@section('subtitle', 'Mỗi lần gửi tới Bing/Yandex/Seznam — 1 dòng là 1 lần gọi API, không phải 1 URL.')

@section('content')

    @if ($paginator->isEmpty())
        <div class="seo-empty">
            Chưa có lần gửi nào. Chạy <code>php artisan seo:indexnow /duong-dan</code> sau khi bật IndexNow trong
            trang Cấu hình.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Số lượng</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $row)
                        @php $urls = json_decode($row->urls, true) ?? []; @endphp
                        <tr>
                            <td class="seo-mono" style="max-width:360px">
                                {{ implode(', ', array_slice($urls, 0, 2)) }}
                                @if (count($urls) > 2)
                                    <span class="seo-muted">+{{ count($urls) - 2 }} nữa</span>
                                @endif
                            </td>
                            <td>{{ $row->url_count }}</td>
                            <td>
                                @if ($row->successful)
                                    <span class="seo-pill is-ok">Thành công @if($row->status_code) ({{ $row->status_code }}) @endif</span>
                                @else
                                    <span class="seo-pill is-bad" title="{{ $row->error }}">
                                        Lỗi @if($row->status_code) ({{ $row->status_code }}) @endif
                                    </span>
                                @endif
                            </td>
                            <td class="seo-muted">{{ $row->created_at }}</td>
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
