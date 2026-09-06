@extends('seo::panel.layout')

@section('title', 'Hoạt động AI')
@section('subtitle', 'Mỗi lần một AI agent gọi tool — đề xuất và áp dụng, qua REST hoặc MCP.')

@section('content')

    @if (session('seo_status'))
        <div class="seo-status is-ok">{{ session('seo_status') }}</div>
    @endif

    @if (session('seo_ai_error'))
        <div class="seo-status is-error">{{ session('seo_ai_error') }}</div>
    @endif

    @if (isset($pending) && $pending->isNotEmpty())
        <div class="seo-card" style="padding:12px 16px">
            <strong style="font-size:13px">Đề xuất đang chờ xác nhận</strong>
            <p class="seo-muted" style="font-size:12.5px;margin:4px 0 10px">
                Một AI agent đã đề xuất những hành động này — chưa có gì thay đổi. Xác nhận để thực sự áp dụng,
                hoặc bỏ qua để đề xuất tự hết hạn.
            </p>
            <div class="seo-table-wrap">
                <table class="seo-table">
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Nội dung đề xuất</th>
                            <th>Thời gian</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $row)
                            <tr>
                                <td class="seo-mono">{{ $row->tool }}</td>
                                <td>{{ $row->preview ?? '(không có mô tả)' }}</td>
                                <td class="seo-muted">{{ $row->created_at }}</td>
                                <td style="white-space:nowrap">
                                    <form method="POST"
                                          action="{{ route('seo.panel.ai-tool-calls.confirm', $row->proposal_id) }}"
                                          style="display:inline"
                                          onsubmit="return confirm('Áp dụng đề xuất này ngay bây giờ?')">
                                        @csrf
                                        <button type="submit" class="seo-btn seo-btn-sm seo-btn-primary">Xác nhận</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($paginator->isEmpty())
        <div class="seo-empty">
            Chưa có lần gọi tool AI nào. Gắn một AI agent (Claude Code, Claude Desktop, hoặc gọi thẳng
            <code>/api/seo/v1/ai/tools</code>) để bắt đầu.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Tool</th>
                        <th>Mức rủi ro</th>
                        <th>Trạng thái</th>
                        <th>Phạm vi</th>
                        <th>Thời gian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $row)
                        <tr>
                            <td class="seo-mono" title="{{ $row->preview }}">{{ $row->tool }}</td>
                            <td>
                                <span class="seo-pill {{ $row->risk_tier === 'destructive' ? 'is-bad' : ($row->risk_tier === 'write' ? 'is-warn' : 'is-ok') }}">
                                    {{ $row->risk_tier }}
                                </span>
                            </td>
                            <td>
                                @if ($row->status === 'applied')
                                    <span class="seo-pill is-ok">Đã áp dụng</span>
                                @else
                                    <span class="seo-pill is-warn">Đã đề xuất</span>
                                @endif
                            </td>
                            <td class="seo-muted">{{ $row->scope ?? '—' }}</td>
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
