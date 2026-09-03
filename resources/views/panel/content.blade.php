@extends('seo::panel.layout')

@section('title', 'Nội dung')
@section('subtitle', 'Meta đã áp dụng thực tế cho từng bản ghi, kể cả khi không có gì được lưu riêng.')

@section('content')

    @if (count($exposedTypes) > 1)
        <div class="seo-card" style="padding:12px 16px">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($exposedTypes as $t)
                    <a href="{{ route('seo.panel.content', ['type' => $t]) }}"
                       class="seo-btn seo-btn-sm {{ $type === $t ? 'seo-btn-primary' : 'seo-btn-secondary' }}">
                        {{ $t }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($type === null)
        <div class="seo-card">
            <p class="seo-muted" style="margin:0">
                Chưa có model nào được expose. Thêm vào <code>seo.api.models</code> trong <code>config/seo.php</code>.
            </p>
        </div>
    @elseif (empty($rows))
        <div class="seo-empty">Không có bản ghi nào.</div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Tiêu đề</th>
                        <th>Mô tả</th>
                        <th>Robots</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>
                                @if ($row['title'])
                                    {{ $row['title'] }}
                                @else
                                    <span class="seo-pill is-warn">Chưa có tiêu đề</span>
                                @endif
                                <div class="seo-muted seo-mono" style="margin-top:2px;font-size:11px">{{ $row['url'] }}</div>
                            </td>
                            <td class="seo-muted">
                                {{ $row['description'] ? \Illuminate\Support\Str::limit($row['description'], 80) : '—' }}
                            </td>
                            <td>
                                @if ($row['robots'])
                                    <span class="seo-pill {{ str_contains($row['robots'], 'noindex') ? 'is-bad' : 'is-neutral' }}">
                                        {{ $row['robots'] }}
                                    </span>
                                @else
                                    <span class="seo-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <a class="seo-btn seo-btn-sm seo-btn-secondary"
                                   href="{{ route('seo.panel.show', ['type' => $type, 'id' => $row['id']]) }}">
                                    Sửa
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($paginator->hasPages())
            {{-- Laravel's default paginator view pulls in Tailwind utility
                 classes; this panel deliberately requires none, so the pager
                 is rendered by hand with the same seo-btn styling as the rest
                 of the page. --}}
            <div style="display:flex;align-items:center;gap:8px;margin-top:16px">
                @if ($paginator->previousPageUrl())
                    <a class="seo-btn seo-btn-sm seo-btn-secondary" href="{{ $paginator->previousPageUrl() }}">← Trước</a>
                @endif

                <span class="seo-muted" style="font-size:12px">
                    Trang {{ $paginator->currentPage() }}/{{ $paginator->lastPage() }}
                    ({{ number_format($paginator->total()) }} bản ghi)
                </span>

                @if ($paginator->nextPageUrl())
                    <a class="seo-btn seo-btn-sm seo-btn-secondary" href="{{ $paginator->nextPageUrl() }}">Sau →</a>
                @endif
            </div>
        @endif
    @endif

@endsection
