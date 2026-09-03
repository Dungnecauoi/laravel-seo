@extends('seo::panel.layout')

@section('title', 'Chuyển hướng')
@section('subtitle', 'Rule được kiểm tra khi request đã 404 — route đang sống không bao giờ bị che.')

@section('content')

    @if (session('seo_status'))
        <div class="seo-status is-ok">{{ session('seo_status') }}</div>
    @endif

    @if ($errors->any())
        <div class="seo-status is-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="seo-card">
        <h3 style="margin-top:0">Thêm / sửa redirect</h3>
        <p class="seo-muted" style="margin-top:-8px;font-size:12.5px">
            Nhập lại đúng nguồn (source) đã có để sửa target — không cần trang riêng.
        </p>

        <form method="POST" action="{{ route('seo.panel.redirects.store') }}">
            @csrf
            <div class="seo-form-row">
                <div class="seo-field">
                    <label class="seo-label">Nguồn</label>
                    <input class="seo-input" type="text" name="source" placeholder="/duong-dan-cu" value="{{ old('source') }}" required>
                </div>
                <div class="seo-field">
                    <label class="seo-label">Đích</label>
                    <input class="seo-input" type="text" name="target" placeholder="/duong-dan-moi" value="{{ old('target') }}">
                </div>
            </div>

            <div class="seo-form-row" style="margin-top:12px">
                <div class="seo-field">
                    <label class="seo-label">Kiểu khớp</label>
                    <select class="seo-select" name="type">
                        <option value="exact" selected>Chính xác</option>
                        <option value="prefix">Tiền tố</option>
                        <option value="regex">Regex</option>
                    </select>
                </div>
                <div class="seo-field">
                    <label class="seo-label">Mã trạng thái</label>
                    <select class="seo-select" name="status">
                        <option value="301" selected>301 — Chuyển vĩnh viễn</option>
                        <option value="302">302 — Tạm thời</option>
                        <option value="307">307 — Tạm thời (giữ method)</option>
                        <option value="308">308 — Vĩnh viễn (giữ method)</option>
                        <option value="410">410 — Đã gỡ bỏ</option>
                        <option value="451">451 — Chặn theo pháp lý</option>
                    </select>
                </div>
                <div class="seo-field">
                    <label class="seo-label">Locale (tuỳ chọn)</label>
                    <input class="seo-input" type="text" name="locale" placeholder="vi" value="{{ old('locale') }}">
                </div>
            </div>

            <div class="seo-field" style="margin-top:12px">
                <label class="seo-label">Ghi chú (tuỳ chọn)</label>
                <input class="seo-input" type="text" name="notes" value="{{ old('notes') }}">
            </div>

            <button type="submit" class="seo-btn seo-btn-primary" style="margin-top:6px">Lưu</button>
        </form>
    </div>

    @if ($paginator->isEmpty())
        <div class="seo-empty">Chưa có redirect nào.</div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Nguồn</th>
                        <th>Đích</th>
                        <th>Mã</th>
                        <th>Kiểu</th>
                        <th>Lượt khớp</th>
                        <th>Trạng thái</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $redirect)
                        <tr>
                            <td class="seo-mono">{{ $redirect->source_path }}</td>
                            <td class="seo-mono">{{ $redirect->target ?? '—' }}</td>
                            <td>{{ $redirect->status_code->value }}</td>
                            <td>{{ $redirect->source_type->value }}</td>
                            <td>{{ number_format($redirect->hits) }}</td>
                            <td>
                                @if ($redirect->is_active)
                                    <span class="seo-pill is-ok">Đang bật</span>
                                @else
                                    <span class="seo-pill is-neutral">Tắt</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap">
                                <form method="POST" action="{{ route('seo.panel.redirects.toggle', $redirect->id) }}" style="display:inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="seo-btn seo-btn-sm seo-btn-secondary">
                                        {{ $redirect->is_active ? 'Tắt' : 'Bật' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('seo.panel.redirects.destroy', $redirect->id) }}"
                                      style="display:inline" onsubmit="return confirm('Xoá redirect này?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="seo-btn seo-btn-sm seo-btn-danger">Xoá</button>
                                </form>
                            </td>
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
