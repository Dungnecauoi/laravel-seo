@extends('seo::panel.layout')

@section('title', 'Tổng quan')
@section('subtitle', 'Trạng thái SEO hiện tại của site.')

@section('content')

    @unless ($seoEnabled)
        <div class="seo-status is-error" role="alert">
            <strong>SEO đang tắt toàn site</strong> (<code>SEO_ENABLED=false</code>). Mọi trang đang bị
            <code>noindex, nofollow</code>, sitemap trống. Đúng cho domain demo — nhớ bật lại trước khi lên production thật.
        </div>
    @endunless

    <div class="seo-stats">
        <div class="seo-stat">
            <div class="seo-stat-value">{{ number_format($totalRecords) }}</div>
            <div class="seo-stat-label">Bản ghi có SEO</div>
        </div>
        <div class="seo-stat">
            <div class="seo-stat-value {{ $totalMissing > 0 ? 'is-warn' : '' }}">{{ number_format($totalMissing) }}</div>
            <div class="seo-stat-label">Chưa có meta riêng</div>
        </div>
        <div class="seo-stat">
            <div class="seo-stat-value">{{ number_format($activeRedirects) }}</div>
            <div class="seo-stat-label">Redirect đang bật</div>
        </div>
        <div class="seo-stat">
            <div class="seo-stat-value {{ $notFoundCount > 0 ? 'is-warn' : '' }}">{{ number_format($notFoundCount) }}</div>
            <div class="seo-stat-label">Link 404 ghi nhận</div>
        </div>
        <div class="seo-stat">
            <div class="seo-stat-value">{{ number_format($sitemapSources) }}</div>
            <div class="seo-stat-label">Nguồn sitemap</div>
        </div>
    </div>

    @if ($exposedTypes === [])
        <div class="seo-card">
            <p class="seo-muted" style="margin:0">
                Chưa có model nào được expose. Thêm vào <code>seo.api.models</code> trong <code>config/seo.php</code>
                để bảng Nội dung và các thao tác của panel có thể truy cập.
            </p>
        </div>
    @else
        <div class="seo-card">
            <h3 style="margin-top:0">Theo loại nội dung</h3>
            <div class="seo-table-wrap">
                <table class="seo-table">
                    <thead>
                        <tr><th>Loại</th><th>Chưa có meta riêng</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($missingByType as $type => $count)
                            <tr>
                                <td class="seo-mono">{{ $type }}</td>
                                <td>
                                    @if ($count > 0)
                                        <span class="seo-pill is-warn">{{ $count }}</span>
                                    @else
                                        <span class="seo-pill is-ok">Đủ</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('seo.panel.content', ['type' => $type]) }}">Xem danh sách →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="seo-card">
        <h3 style="margin-top:0">Kiểm tra trùng lặp</h3>
        <p class="seo-muted" style="margin-bottom:0">
            Panel chỉ cảnh báo trùng title/description ngay lúc lưu. Quét toàn site (bắt cả trường hợp trùng qua
            template mặc định) bằng:
        </p>
        <pre style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 12px;margin:10px 0 0;font-size:12px;overflow-x:auto">php artisan seo:duplicates {App\Models\Post} --field=both</pre>
    </div>

@endsection
