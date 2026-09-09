@extends('seo::panel.layout')

@section('title', 'Link chết')
@section('subtitle', 'URL bên ngoài không còn truy cập được — dữ liệu từ lần chạy php artisan seo:broken-links gần nhất.')

@section('content')

    @if ($paginator->isEmpty())
        <div class="seo-empty">
            Không có link chết nào được ghi nhận. Chạy <code>php artisan seo:broken-links App\Models\Post</code>
            để quét.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Lỗi</th>
                        <th>Số trang trỏ tới</th>
                        <th>Kiểm tra lúc</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $row)
                        <tr>
                            <td class="seo-mono" style="max-width:360px">{{ $row->url }}</td>
                            <td>
                                <span class="seo-pill is-bad" title="{{ $row->error }}">
                                    {{ $row->status_code ? "HTTP {$row->status_code}" : ($row->error ?? 'Lỗi') }}
                                </span>
                            </td>
                            <td>{{ $sourcesByHash[md5($row->url)] ?? 0 }}</td>
                            <td class="seo-muted">{{ $row->updated_at }}</td>
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
