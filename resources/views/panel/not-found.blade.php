@extends('seo::panel.layout')

@section('title', '404 Monitor')
@section('subtitle', 'Đường dẫn không tìm thấy trang — mỗi dòng là một path, không phải một lượt truy cập.')

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

    <div class="seo-card" style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <span class="seo-muted" style="font-size:12.5px">Bot quét thường tạo hàng loạt dòng vô nghĩa — dọn định kỳ.</span>
        <form method="POST" action="{{ route('seo.panel.not-found.prune') }}" style="display:flex;gap:8px;align-items:center">
            @csrf
            <input class="seo-input" style="width:80px" type="number" name="days" value="90" min="1">
            <button type="submit" class="seo-btn seo-btn-sm seo-btn-secondary">Xoá mục cũ hơn (ngày)</button>
        </form>
    </div>

    @if ($paginator->isEmpty())
        <div class="seo-empty">Không có link 404 nào được ghi nhận.</div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Đường dẫn</th>
                        <th>Lượt</th>
                        <th>Thấy lần cuối</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paginator as $row)
                        <tr>
                            {{-- Escaped explicitly: this is attacker-supplied
                                 text, not content the panel itself produced. --}}
                            <td class="seo-mono">{{ $row->path }}</td>
                            <td>{{ number_format($row->hits) }}</td>
                            <td class="seo-muted">{{ $row->last_seen_at }}</td>
                            <td style="white-space:nowrap">
                                <form method="POST" action="{{ route('seo.panel.not-found.redirect', $row->id) }}" style="display:inline-flex;gap:6px">
                                    @csrf
                                    <input class="seo-input seo-btn-sm" style="width:160px" type="text" name="target" placeholder="Chuyển tới…" required>
                                    <button type="submit" class="seo-btn seo-btn-sm seo-btn-secondary">Tạo redirect</button>
                                </form>
                                <form method="POST" action="{{ route('seo.panel.not-found.destroy', $row->id) }}"
                                      style="display:inline" onsubmit="return confirm('Xoá dòng này?')">
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
