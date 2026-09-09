@extends('seo::panel.layout')

@section('title', 'PageSpeed')
@section('subtitle', 'Điểm hiệu năng và Core Web Vitals — dữ liệu từ lần chạy php artisan seo:pagespeed gần nhất cho mỗi URL.')

@section('content')

    <div class="seo-card" style="padding:12px 16px;display:flex;gap:8px;flex-wrap:wrap">
        @foreach (['mobile' => 'Mobile', 'desktop' => 'Desktop'] as $value => $label)
            <a href="{{ route('seo.panel.pagespeed', ['strategy' => $value]) }}"
               class="seo-btn seo-btn-sm {{ $strategy === $value ? 'seo-btn-primary' : 'seo-btn-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($rows->isEmpty())
        <div class="seo-empty">
            Chưa có dữ liệu. Chạy <code>php artisan seo:pagespeed https://vidu.vn/trang</code> sau khi cấu hình
            API key trong <code>seo.pagespeed</code>.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Trang</th>
                        <th>Điểm</th>
                        <th>LCP</th>
                        <th>CLS</th>
                        <th>TBT</th>
                        <th>CWV (thực tế)</th>
                        <th>Ngày</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="seo-mono">{{ $row->url }}</td>
                            <td>
                                @php $score = $row->performance_score; @endphp
                                @if ($score === null)
                                    —
                                @else
                                    <span class="seo-pill {{ $score >= 90 ? 'is-ok' : ($score >= 50 ? '' : 'is-bad') }}">
                                        {{ $score }}/100
                                    </span>
                                @endif
                            </td>
                            <td>{{ $row->lcp_ms !== null ? number_format($row->lcp_ms / 1000, 1).'s' : '—' }}</td>
                            <td>{{ $row->cls_score !== null ? number_format($row->cls_score, 3) : '—' }}</td>
                            <td>{{ $row->tbt_ms !== null ? number_format($row->tbt_ms).'ms' : '—' }}</td>
                            <td>
                                @if (! $row->field_data_available)
                                    <span class="seo-muted">Chưa đủ dữ liệu</span>
                                @else
                                    {{ $row->cwv_category }}
                                @endif
                            </td>
                            <td class="seo-muted">{{ $row->date }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
