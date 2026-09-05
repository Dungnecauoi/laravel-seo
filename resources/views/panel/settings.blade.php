@extends('seo::panel.layout')

@section('title', 'Cấu hình')
@section('subtitle', 'Chỉ mục hoá và bề mặt truy cập ở dưới là trạng thái thực tế của config/seo.php — sửa trong file, deploy lại để áp dụng. Phần chỉnh được ở cuối trang lưu ngay, không cần deploy.')

@push('head')
    <style>
        .seo-settings-group { margin: 20px 0 4px; font-size: 13px; font-weight: 700; color: #0f172a; }
        .seo-settings-group:first-of-type { margin-top: 0; }
        .seo-checkbox-field { display: flex; align-items: center; gap: 8px; font-size: 13px; margin-bottom: 14px; }
        .seo-checkbox-field input { width: 16px; height: 16px; }
        .seo-secret-status { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-size: 12px; }
    </style>
@endpush

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

    @if (! $dynamicSettingsEnabled)
        <div class="seo-card">
            <p class="seo-muted" style="margin:0">
                Cấu hình động đang tắt. Bật <code>seo.settings.enabled</code> trong <code>config/seo.php</code>
                để chỉnh các mục bên dưới ngay trên panel này, lưu là áp dụng luôn, không cần deploy lại.
            </p>
        </div>
    @else
        @php
            // Direct array access, not data_get(): $dynamicSettings is keyed
            // by the dotted setting name as one flat string ('defaults.title'),
            // not a nested structure — data_get() would split that key on
            // every dot and look for a nested 'defaults' => 'title' array
            // that does not exist, silently returning null for every key
            // with more than one segment.
            $val = fn (string $k) => $dynamicSettings[$k]['value'] ?? null;
            $isSet = fn (string $k) => $dynamicSettings[$k]['is_set'] ?? false;
            $field = fn (string $k) => str_replace('.', '__', $k);
        @endphp

        <div class="seo-card">
            <h3 style="margin-top:0">Chỉnh cấu hình</h3>
            <p class="seo-muted" style="margin-top:-8px;font-size:12.5px">
                Lưu ở đây ghi đè <code>config/seo.php</code> ngay lập tức. Để trống một ô rồi lưu để quay lại
                giá trị mặc định trong file.
            </p>

            @if (session('seo_status'))
                <div class="seo-status is-ok">{{ session('seo_status') }}</div>
            @endif

            <form method="POST" action="{{ route('seo.panel.settings.update') }}">
                @csrf
                @method('PUT')

                <p class="seo-settings-group">Chung</p>
                <label class="seo-checkbox-field">
                    <input type="checkbox" name="{{ $field('enabled') }}" value="1" {{ $val('enabled') ? 'checked' : '' }}>
                    SEO đang bật cho toàn site
                </label>
                <div class="seo-field">
                    <label class="seo-label">Tên site</label>
                    <input class="seo-input" type="text" name="{{ $field('site_name') }}" value="{{ $val('site_name') }}">
                </div>

                <p class="seo-settings-group">Meta mặc định</p>
                <div class="seo-form-row">
                    <div class="seo-field">
                        <label class="seo-label">Tiêu đề mặc định</label>
                        <input class="seo-input" type="text" name="{{ $field('defaults.title') }}" value="{{ $val('defaults.title') }}">
                    </div>
                    <div class="seo-field">
                        <label class="seo-label">Robots mặc định</label>
                        <input class="seo-input" type="text" name="{{ $field('defaults.robots') }}" value="{{ $val('defaults.robots') }}" placeholder="max-image-preview:large">
                    </div>
                </div>
                <div class="seo-field">
                    <label class="seo-label">Mô tả mặc định</label>
                    <textarea class="seo-textarea" name="{{ $field('defaults.description') }}" rows="2">{{ $val('defaults.description') }}</textarea>
                </div>
                <div class="seo-form-row">
                    <div class="seo-field">
                        <label class="seo-label">og:site_name mặc định</label>
                        <input class="seo-input" type="text" name="{{ $field('defaults.og.site_name') }}" value="{{ $val('defaults.og.site_name') }}">
                    </div>
                    <div class="seo-field">
                        <label class="seo-label">twitter:card mặc định</label>
                        <select class="seo-select" name="{{ $field('defaults.twitter.card') }}">
                            @foreach (['summary', 'summary_large_image'] as $card)
                                <option value="{{ $card }}" {{ $val('defaults.twitter.card') === $card ? 'selected' : '' }}>{{ $card }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <p class="seo-settings-group">Xác minh Search Console / Webmaster</p>
                <div class="seo-form-row">
                    <div class="seo-field">
                        <label class="seo-label">Google</label>
                        <input class="seo-input" type="text" name="{{ $field('verification.google') }}" value="{{ $val('verification.google') }}">
                    </div>
                    <div class="seo-field">
                        <label class="seo-label">Bing</label>
                        <input class="seo-input" type="text" name="{{ $field('verification.bing') }}" value="{{ $val('verification.bing') }}">
                    </div>
                </div>
                <div class="seo-form-row">
                    <div class="seo-field">
                        <label class="seo-label">Yandex</label>
                        <input class="seo-input" type="text" name="{{ $field('verification.yandex') }}" value="{{ $val('verification.yandex') }}">
                    </div>
                    <div class="seo-field">
                        <label class="seo-label">Pinterest</label>
                        <input class="seo-input" type="text" name="{{ $field('verification.pinterest') }}" value="{{ $val('verification.pinterest') }}">
                    </div>
                    <div class="seo-field">
                        <label class="seo-label">Facebook</label>
                        <input class="seo-input" type="text" name="{{ $field('verification.facebook') }}" value="{{ $val('verification.facebook') }}">
                    </div>
                </div>

                <p class="seo-settings-group">Robots &amp; Schema.org</p>
                <label class="seo-checkbox-field">
                    <input type="checkbox" name="{{ $field('robots.block_ai_crawlers') }}" value="1" {{ $val('robots.block_ai_crawlers') ? 'checked' : '' }}>
                    Chặn bot huấn luyện AI (GPTBot, ClaudeBot…) trong robots.txt — không ảnh hưởng Googlebot/Bingbot
                </label>
                <div class="seo-form-row">
                    <div class="seo-field">
                        <label class="seo-label">Tên tổ chức (Organization)</label>
                        <input class="seo-input" type="text" name="{{ $field('schema.organization.name') }}" value="{{ $val('schema.organization.name') }}">
                    </div>
                    <div class="seo-field">
                        <label class="seo-label">Logo (URL)</label>
                        <input class="seo-input" type="text" name="{{ $field('schema.organization.logo') }}" value="{{ $val('schema.organization.logo') }}">
                    </div>
                </div>
                <div class="seo-field">
                    <label class="seo-label">Mạng xã hội (sameAs — mỗi dòng một URL)</label>
                    <textarea class="seo-textarea" name="{{ $field('schema.organization.sameAs') }}" rows="3" placeholder="https://facebook.com/...
https://youtube.com/...">{{ implode("\n", (array) $val('schema.organization.sameAs')) }}</textarea>
                </div>
                <div class="seo-field">
                    <label class="seo-label">URL tìm kiếm nội bộ (sitelinks search box)</label>
                    <input class="seo-input" type="text" name="{{ $field('schema.website.search_url') }}" value="{{ $val('schema.website.search_url') }}" placeholder="/tim-kiem?q={search_term_string}">
                </div>

                <p class="seo-settings-group">IndexNow</p>
                <label class="seo-checkbox-field">
                    <input type="checkbox" name="{{ $field('indexnow.enabled') }}" value="1" {{ $val('indexnow.enabled') ? 'checked' : '' }}>
                    Bật gửi IndexNow (Bing, Yandex, Seznam)
                </label>
                <div class="seo-field">
                    <label class="seo-label">Key</label>
                    <input class="seo-input seo-mono" type="text" name="{{ $field('indexnow.key') }}" value="{{ $val('indexnow.key') }}">
                </div>

                <p class="seo-settings-group">Search Console API</p>
                <label class="seo-checkbox-field">
                    <input type="checkbox" name="{{ $field('search_console.enabled') }}" value="1" {{ $val('search_console.enabled') ? 'checked' : '' }}>
                    Bật đồng bộ số liệu Search Console
                </label>
                <div class="seo-form-row">
                    <div class="seo-field">
                        <label class="seo-label">Client ID</label>
                        <input class="seo-input" type="text" name="{{ $field('search_console.client_id') }}" value="{{ $val('search_console.client_id') }}">
                    </div>
                    <div class="seo-field">
                        <label class="seo-label">Site URL</label>
                        <input class="seo-input" type="text" name="{{ $field('search_console.site_url') }}" value="{{ $val('search_console.site_url') }}" placeholder="https://trangcuatoi.vn/">
                    </div>
                </div>
                <div class="seo-form-row">
                    <div class="seo-field">
                        <div class="seo-secret-status">
                            <label class="seo-label" style="margin:0">Client Secret</label>
                            @if ($isSet('search_console.client_secret'))
                                <span class="seo-pill is-ok">Đã đặt</span>
                            @else
                                <span class="seo-pill is-neutral">Chưa đặt</span>
                            @endif
                        </div>
                        <input class="seo-input" type="password" name="{{ $field('search_console.client_secret') }}" placeholder="Để trống = giữ nguyên" autocomplete="new-password">
                    </div>
                    <div class="seo-field">
                        <div class="seo-secret-status">
                            <label class="seo-label" style="margin:0">Refresh Token</label>
                            @if ($isSet('search_console.refresh_token'))
                                <span class="seo-pill is-ok">Đã đặt</span>
                            @else
                                <span class="seo-pill is-neutral">Chưa đặt</span>
                            @endif
                        </div>
                        <input class="seo-input" type="password" name="{{ $field('search_console.refresh_token') }}" placeholder="Để trống = giữ nguyên" autocomplete="new-password">
                    </div>
                </div>

                <div style="border-top:1px solid #e2e8f0;padding-top:16px;margin-top:6px">
                    <button type="submit" class="seo-btn seo-btn-primary">Lưu cấu hình</button>
                </div>
            </form>
        </div>
    @endif

@endsection
