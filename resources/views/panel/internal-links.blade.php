@extends('seo::panel.layout')

@section('title', 'Liên kết nội bộ')
@section('subtitle', 'Bao nhiêu link nội bộ trỏ tới từng trang — dữ liệu từ lần chạy php artisan seo:internal-links gần nhất.')

@section('content')

    @if (count($exposedTypes) > 1)
        <div class="seo-card" style="padding:12px 16px">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @foreach ($exposedTypes as $t)
                    <a href="{{ route('seo.panel.internal-links', ['type' => $t]) }}"
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
        <div class="seo-empty">
            Không có bản ghi nào. Chạy <code>php artisan seo:internal-links {{ $type }} --content=body</code> để quét link.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Trang</th>
                        <th>Link đến (incoming)</th>
                        <th>Link đi (outgoing)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="seo-mono">{{ $row['url'] }}</td>
                            <td>{{ $row['incoming'] }}</td>
                            <td>{{ $row['outgoing'] }}</td>
                            <td>
                                @if ($row['isOrphan'])
                                    <span class="seo-pill is-bad">Mồ côi — không ai link tới</span>
                                @else
                                    <span class="seo-pill is-ok">OK</span>
                                @endif
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
                    ({{ number_format($paginator->total()) }} bản ghi)
                </span>
                @if ($paginator->nextPageUrl())
                    <a class="seo-btn seo-btn-sm seo-btn-secondary" href="{{ $paginator->nextPageUrl() }}">Sau →</a>
                @endif
            </div>
        @endif
    @endif

@endsection
