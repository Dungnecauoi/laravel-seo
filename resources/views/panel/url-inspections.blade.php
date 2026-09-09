@extends('seo::panel.layout')

@section('title', 'Kiểm tra URL')
@section('subtitle', 'Google có index trang này không, và vì sao không — dữ liệu từ lần chạy php artisan seo:search-console:inspect gần nhất.')

@section('content')

    @if ($rows->isEmpty())
        <div class="seo-empty">
            Chưa có dữ liệu. Chạy <code>php artisan seo:search-console:inspect https://vidu.vn/trang</code> sau khi
            cấu hình Search Console trong trang Cấu hình.
        </div>
    @else
        <div class="seo-table-wrap">
            <table class="seo-table">
                <thead>
                    <tr>
                        <th>Trang</th>
                        <th>Trạng thái</th>
                        <th>Verdict</th>
                        <th>Mobile</th>
                        <th>Rich results</th>
                        <th>Ngày</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $issues = json_decode($row->mobile_usability_issues ?? '[]', true) ?? []; @endphp
                        <tr>
                            <td class="seo-mono">{{ $row->url }}</td>
                            <td>{{ $row->coverage_state ?? '—' }}</td>
                            <td>
                                @php $verdict = $row->verdict; @endphp
                                @if ($verdict === 'PASS')
                                    <span class="seo-pill is-ok">PASS</span>
                                @elseif ($verdict === 'FAIL')
                                    <span class="seo-pill is-bad">FAIL</span>
                                @else
                                    <span class="seo-muted">{{ $verdict ?? '—' }}</span>
                                @endif
                            </td>
                            <td title="{{ implode(', ', $issues) }}">
                                {{ $row->mobile_usability_verdict ?? '—' }}
                                @if (count($issues) > 0)
                                    <span class="seo-muted">({{ count($issues) }} lỗi)</span>
                                @endif
                            </td>
                            <td>{{ $row->rich_results_verdict ?? '—' }}</td>
                            <td class="seo-muted">{{ $row->date }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

@endsection
