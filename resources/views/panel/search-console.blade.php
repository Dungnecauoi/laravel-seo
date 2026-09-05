@extends('seo::panel.layout')

@section('title', 'Search Console')
@section('subtitle', 'Click, impression, vị trí trung bình mỗi trang — dữ liệu từ lần chạy php artisan seo:search-console:sync gần nhất.')

@section('content')

    <div class="seo-card" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach ([7, 30, 90] as $option)
                <a href="{{ route('seo.panel.search-console', ['days' => $option]) }}"
                   class="seo-btn seo-btn-sm {{ $days === $option ? 'seo-btn-primary' : 'seo-btn-secondary' }}">
                    {{ $option }} ngày
                </a>
            @endforeach
        </div>
        <span class="seo-muted" style="font-size:12.5px">
            Tổng {{ number_format($totalClicks) }} click, {{ number_format($totalImpressions) }} impression trong {{ $days }} ngày.
        </span>
    </div>

    @if ($rows->isEmpty())
        <div class="seo-empty">
            Chưa có dữ liệu. Chạy <code>php artisan seo:search-console:sync</code> sau khi cấu hình Search Console
            trong trang Cấu hình.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Trang</th>
                        <th>Click</th>
                        <th>Impression</th>
                        <th>CTR</th>
                        <th>Vị trí TB</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="seo-mono">{{ $row->url }}</td>
                            <td>{{ number_format($row->clicks) }}</td>
                            <td>{{ number_format($row->impressions) }}</td>
                            <td>{{ $row->impressions > 0 ? number_format($row->clicks / $row->impressions * 100, 1).'%' : '—' }}</td>
                            <td>{{ $row->position !== null ? number_format($row->position, 1) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
