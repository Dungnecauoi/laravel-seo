@extends('seo::panel.layout')

@section('title', 'Cấu hình')
@section('subtitle', 'Chỉ xem — sửa trong config/seo.php, deploy lại để áp dụng.')

@section('content')

    <div class="seo-card">
        <h3 style="margin-top:0">Chỉ mục hoá</h3>
        <table class="seo-table">
            <tbody>
                <tr>
                    <td style="width:220px">Master switch (<code>seo.enabled</code>)</td>
                    <td>
                        @if ($seoEnabled)
                            <span class="seo-pill is-ok">Bật</span>
                        @else
                            <span class="seo-pill is-bad">Tắt — noindex toàn site</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Môi trường hiện tại</td>
                    <td class="seo-mono">{{ $currentEnvironment }}</td>
                </tr>
                <tr>
                    <td>Môi trường cho index</td>
                    <td class="seo-mono">{{ implode(', ', $indexableEnvironments) ?: '—' }}</td>
                </tr>
                <tr>
                    <td>Locale hỗ trợ</td>
                    <td class="seo-mono">{{ implode(', ', $supportedLocales) ?: '(1 ngôn ngữ)' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="seo-card">
        <h3 style="margin-top:0">Bề mặt truy cập</h3>
        <table class="seo-table">
            <tbody>
                <tr>
                    <td style="width:220px">REST API (<code>/api/seo/v1</code>)</td>
                    <td>
                        <span class="seo-pill {{ $apiEnabled ? 'is-ok' : 'is-neutral' }}">
                            {{ $apiEnabled ? 'Bật' : 'Tắt' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>Blade panel</td>
                    <td><span class="seo-pill is-ok">Bật</span> (đang xem trang này)</td>
                </tr>
                <tr>
                    <td>Model được expose</td>
                    <td class="seo-mono">{{ implode(', ', $exposedModels) ?: 'Chưa có' }}</td>
                </tr>
                <tr>
                    <td>Host redirect/canonical được phép</td>
                    <td class="seo-mono">{{ implode(', ', $allowedHosts) ?: '(chỉ domain của app)' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="seo-card">
        <h3 style="margin-top:0">Sitemap &amp; AI</h3>
        <table class="seo-table">
            <tbody>
                <tr>
                    <td style="width:220px">Nguồn sitemap đã đăng ký</td>
                    <td>{{ $sitemapSourceCount }}</td>
                </tr>
                <tr>
                    <td>AI driver mặc định</td>
                    <td class="seo-mono">{{ $aiDriver }}</td>
                </tr>
                <tr>
                    <td>Ngân sách token AI/ngày</td>
                    <td>{{ $aiBudget > 0 ? number_format($aiBudget) : 'Không giới hạn' }}</td>
                </tr>
                <tr>
                    <td>Rate limit /analyze</td>
                    <td class="seo-mono">{{ $analysisRateLimit }}</td>
                </tr>
            </tbody>
        </table>
    </div>

@endsection
