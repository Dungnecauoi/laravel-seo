<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SEO — @yield('title', 'Bảng điều khiển')</title>
    <style>
        /*
         * Scoped under .seo-shell and prefixed `seo-`, so this page never
         * depends on the host project having Tailwind configured — a Blade
         * admin panel ships to projects that may have no front-end build
         * step at all, unlike the React and Vue packages.
         */
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        a { color: inherit; }

        .seo-shell { display: flex; min-height: 100vh; }

        .seo-nav {
            width: 220px;
            flex: none;
            background: #0f172a;
            color: #cbd5e1;
            padding: 20px 0;
        }
        .seo-nav-brand {
            font-weight: 700;
            font-size: 15px;
            color: #fff;
            padding: 0 20px 16px;
            margin-bottom: 8px;
            border-bottom: 1px solid #1e293b;
        }
        .seo-nav-link {
            display: block;
            padding: 9px 20px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 13px;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .seo-nav-link:hover { color: #e2e8f0; background: #1e293b; }
        .seo-nav-link.is-active {
            color: #fff;
            background: #1e293b;
            border-left-color: #38bdf8;
        }
        .seo-nav-badge {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            border-radius: 999px;
            background: #dc2626;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            line-height: 1.5;
        }

        .seo-main { flex: 1; min-width: 0; padding: 32px 40px 64px; }
        .seo-page-title { font-size: 20px; font-weight: 700; margin: 0 0 4px; }
        .seo-page-sub { color: #64748b; font-size: 13px; margin: 0 0 24px; }

        .seo-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .seo-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .seo-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 18px; }
        .seo-stat-value { font-size: 24px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .seo-stat-value.is-warn { color: #b45309; }
        .seo-stat-value.is-bad { color: #b91c1c; }
        .seo-stat-label { font-size: 12px; color: #64748b; margin-top: 2px; }

        .seo-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .seo-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #64748b;
            font-weight: 600;
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .seo-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .seo-table tr:last-child td { border-bottom: none; }
        .seo-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
        .seo-table-wrap .seo-table { border: none; border-radius: 0; }
        .seo-muted { color: #94a3b8; }
        .seo-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }

        .seo-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .seo-pill.is-ok { background: #dcfce7; color: #166534; }
        .seo-pill.is-warn { background: #fef3c7; color: #92400e; }
        .seo-pill.is-bad { background: #fee2e2; color: #991b1b; }
        .seo-pill.is-neutral { background: #f1f5f9; color: #475569; }

        .seo-btn {
            display: inline-block;
            border: 1px solid transparent;
            border-radius: 6px;
            padding: 7px 14px;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .seo-btn:disabled { cursor: not-allowed; opacity: .5; }
        .seo-btn-primary { background: #0f172a; color: #fff; }
        .seo-btn-secondary { background: #fff; border-color: #cbd5e1; color: #334155; }
        .seo-btn-danger { background: #fff; border-color: #fecaca; color: #b91c1c; }
        .seo-btn-sm { padding: 4px 10px; font-size: 12px; }

        .seo-input, .seo-select, .seo-textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
            font-size: 13px;
            color: inherit;
            background: #fff;
        }
        .seo-input:focus, .seo-select:focus, .seo-textarea:focus {
            outline: none;
            border-color: #64748b;
            box-shadow: 0 0 0 1px #64748b;
        }
        .seo-field { margin-bottom: 14px; }
        .seo-label { display: block; font-weight: 600; color: #334155; margin-bottom: 5px; font-size: 12.5px; }

        .seo-form-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .seo-form-row .seo-field { flex: 1; min-width: 160px; margin-bottom: 0; }

        .seo-status { margin: 0 0 16px; padding: 10px 14px; border-radius: 6px; font-size: 13px; }
        .seo-status.is-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .seo-status.is-ok { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        .seo-empty { padding: 40px 20px; text-align: center; color: #94a3b8; font-size: 13px; }

        @media (max-width: 720px) {
            .seo-shell { flex-direction: column; }
            .seo-nav { width: 100%; display: flex; overflow-x: auto; padding: 10px 0; }
            .seo-nav-brand { display: none; }
            .seo-nav-link { border-left: none; border-bottom: 3px solid transparent; white-space: nowrap; }
            .seo-nav-link.is-active { border-left-color: transparent; border-bottom-color: #38bdf8; }
            .seo-main { padding: 20px; }
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="seo-shell">
        <nav class="seo-nav">
            <div class="seo-nav-brand">SEO</div>
            @php
                $navItems = [
                    'seo.panel.dashboard' => 'Tổng quan',
                    'seo.panel.content' => 'Nội dung',
                    'seo.panel.redirects.index' => 'Chuyển hướng',
                    'seo.panel.not-found.index' => '404',
                    'seo.panel.settings' => 'Cấu hình',
                ];
            @endphp
            @foreach ($navItems as $routeName => $label)
                @if (\Illuminate\Support\Facades\Route::has($routeName))
                    <a href="{{ route($routeName) }}"
                       class="seo-nav-link {{ request()->routeIs($routeName) ? 'is-active' : '' }}">
                        {{ $label }}
                        @if ($routeName === 'seo.panel.not-found.index' && ($notFoundCount ?? 0) > 0)
                            <span class="seo-nav-badge">{{ $notFoundCount }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>

        <main class="seo-main">
            <h1 class="seo-page-title">@yield('title', 'Tổng quan')</h1>
            @hasSection('subtitle')
                <p class="seo-page-sub">@yield('subtitle')</p>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
