@extends('seo::panel.layout')

@section('title', 'Google Indexing')
@section('subtitle', 'Mỗi dòng là 1 URL — API của Google chỉ nhận từng URL một lượt, không gộp batch như IndexNow.')

@section('content')

    @if ($paginator->isEmpty())
        <div class="seo-empty">
            Chưa có lần gửi nào. Chạy <code>php artisan seo:google-indexing /duong-dan</code> sau khi cấu hình
            service account trong <code>seo.google_indexing</code>.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Loại</th>
                        <th>Trạng thái</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $row)
                        <tr>
                            <td class="seo-mono" style="max-width:360px">{{ $row->url }}</td>
                            <td>{{ $row->type === 'URL_DELETED' ? 'Đã xoá' : 'Cập nhật' }}</td>
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
