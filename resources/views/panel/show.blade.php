@extends('seo::panel.layout')

@section('title', $type.' #'.$id)
@section('subtitle', $seoUrl)

@push('head')
    <style>
        /* Page-specific — everything shared (buttons, fields, status
           banners) already comes from the layout. */
        .seo-textarea { resize: vertical; min-height: 72px; }

        .seo-hint { margin: 6px 0 0; font-size: 12px; color: #94a3b8; }
        .seo-hint.is-ok { color: #059669; }
        .seo-hint.is-warn { color: #d97706; }

        .seo-score {
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .seo-score-value { font-weight: 600; }
        .seo-score-note { font-size: 12px; color: #64748b; margin: 2px 0 0; }

        .seo-checks { list-style: none; margin: 12px 0 0; padding: 12px 0 0; border-top: 1px solid #f1f5f9; }
        .seo-check { display: flex; gap: 8px; align-items: flex-start; font-size: 12px; margin-bottom: 6px; }
        .seo-check-dot { width: 6px; height: 6px; border-radius: 999px; margin-top: 5px; flex: none; }
        .seo-check.is-fail .seo-check-dot { background: #dc2626; }
        .seo-check.is-fail { color: #b91c1c; }
        .seo-check.is-warning .seo-check-dot { background: #d97706; }
        .seo-check.is-warning { color: #b45309; }

        .seo-actions { display: flex; align-items: center; gap: 12px; padding-top: 20px; border-top: 1px solid #e2e8f0; margin-top: 4px; }
        .seo-dirty-flag { font-size: 12px; color: #b45309; }
    </style>
@endpush

@section('content')

    <div id="seo-editor" style="max-width:560px">
        <div id="seo-status"></div>

        <label class="seo-field">
            <span class="seo-label">Tiêu đề</span>
            <input class="seo-input" type="text" id="seo-title" placeholder="Để trống dùng giá trị mặc định">
            <p class="seo-hint" id="seo-title-hint">0 ký tự</p>
        </label>

        <label class="seo-field">
            <span class="seo-label">Mô tả</span>
            <textarea class="seo-textarea seo-input" id="seo-description" rows="3"></textarea>
            <p class="seo-hint" id="seo-description-hint">0 ký tự</p>
        </label>

        <label class="seo-field">
            <span class="seo-label">Từ khoá chính</span>
            <input class="seo-input" type="text" id="seo-keyword">
        </label>

        <div class="seo-score" id="seo-score" hidden>
            <svg width="40" height="40" viewBox="0 0 40 40" id="seo-score-ring">
                <circle cx="20" cy="20" r="16" fill="none" stroke="#e2e8f0" stroke-width="4"></circle>
                <circle id="seo-score-arc" cx="20" cy="20" r="16" fill="none" stroke="#059669" stroke-width="4"
                        stroke-linecap="round" transform="rotate(-90 20 20)"></circle>
            </svg>
            <div>
                <p class="seo-score-value" id="seo-score-value">—/100</p>
                <p class="seo-score-note" id="seo-score-note"></p>
            </div>
        </div>
        <ul class="seo-checks" id="seo-checks" hidden></ul>

        <div class="seo-actions">
            <button type="button" class="seo-btn seo-btn-primary" id="seo-save" disabled>Lưu</button>
            <button type="button" class="seo-btn seo-btn-secondary" id="seo-reset" disabled>Hoàn tác</button>
            <span class="seo-dirty-flag" id="seo-dirty-flag" hidden>Có thay đổi chưa lưu</span>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
    (function () {
        'use strict';

        // A plain IIFE with fetch(), not a bundled app: this page has to run
        // in a project with no front-end build step at all, which is exactly
        // the case the React and Vue packages do not cover.
        var DESCRIPTION_MIN = {{ $descriptionMin }};
        var DESCRIPTION_MAX = {{ $descriptionMax }};
        var DATA_URL = @json($dataUrl);
        var ANALYZE_URL = @json($analyzeUrl);
        var LOCALE = @json($locale);
        var CSRF = document.querySelector('meta[name="csrf-token"]').content;

        var els = {
            status: document.getElementById('seo-status'),
            title: document.getElementById('seo-title'),
            titleHint: document.getElementById('seo-title-hint'),
            description: document.getElementById('seo-description'),
            descriptionHint: document.getElementById('seo-description-hint'),
            keyword: document.getElementById('seo-keyword'),
            score: document.getElementById('seo-score'),
            scoreValue: document.getElementById('seo-score-value'),
            scoreNote: document.getElementById('seo-score-note'),
            scoreArc: document.getElementById('seo-score-arc'),
            checks: document.getElementById('seo-checks'),
            save: document.getElementById('seo-save'),
            reset: document.getElementById('seo-reset'),
            dirtyFlag: document.getElementById('seo-dirty-flag'),
        };

        var stored = {};
        var draft = {};
        var analyzeTimer = null;

        function withQuery(url) {
            return LOCALE ? url + (url.indexOf('?') === -1 ? '?' : '&') + 'locale=' + encodeURIComponent(LOCALE) : url;
        }

        function request(url, options) {
            options = options || {};
            options.headers = Object.assign({
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF,
            }, options.headers || {});

            if (options.body) options.headers['Content-Type'] = 'application/json';

            return fetch(url, options).then(function (response) {
                if (!response.ok) {
                    return response.json().catch(function () { return null; }).then(function (body) {
                        throw new Error((body && body.message) || ('HTTP ' + response.status));
                    });
                }
                return response.status === 204 ? null : response.json();
            });
        }

        function showError(message) {
            els.status.innerHTML = '<p class="seo-status is-error" role="alert"></p>';
            els.status.firstChild.textContent = message;
        }

        function showWarnings(warnings) {
            if (!warnings) return;
            var messages = [];

            if (warnings.duplicate_title) messages.push('Tiêu đề này đã dùng ở bản ghi khác.');
            if (warnings.duplicate_description) messages.push('Mô tả này đã dùng ở bản ghi khác.');
            if (messages.length === 0) return;

            var p = document.createElement('p');
            p.className = 'seo-status is-error';
            p.setAttribute('role', 'alert');
            p.textContent = messages.join(' ');
            els.status.appendChild(p);
        }

        function clearStatus() {
            els.status.innerHTML = '';
        }

        function isBlank(v) { return v === undefined || v === null || v === ''; }

        function isDirty() {
            var keys = ['title', 'description', 'focusKeyword'];
            for (var i = 0; i < keys.length; i++) {
                var a = stored[keys[i]], b = draft[keys[i]];
                if (isBlank(a) && isBlank(b)) continue;
                if (a !== b) return true;
            }
            return false;
        }

        function syncButtons() {
            var dirty = isDirty();
            els.save.disabled = !dirty;
            els.reset.disabled = !dirty;
            els.dirtyFlag.hidden = !dirty;
        }

        function syncHints() {
            var titleLen = (draft.title || '').length;
            els.titleHint.textContent = titleLen + ' ký tự';

            var descLen = (draft.description || '').length;
            var inRange = descLen >= DESCRIPTION_MIN && descLen <= DESCRIPTION_MAX;
            els.descriptionHint.textContent = descLen + ' ký tự (khuyến nghị ' + DESCRIPTION_MIN + '–' + DESCRIPTION_MAX + ')';
            els.descriptionHint.className = 'seo-hint ' + (inRange ? 'is-ok' : 'is-warn');
        }

        function renderScore(report) {
            if (!report) { els.score.hidden = true; els.checks.hidden = true; return; }

            els.score.hidden = false;
            els.scoreValue.textContent = report.score + '/100';

            var problems = (report.results || []).filter(function (r) {
                return r.status === 'fail' || r.status === 'warning';
            });
            els.scoreNote.textContent = problems.length + ' điểm cần chú ý';

            var circumference = 2 * Math.PI * 16;
            els.scoreArc.style.strokeDasharray = String(circumference);
            els.scoreArc.style.strokeDashoffset = String(circumference - (report.score / 100) * circumference);
            els.scoreArc.setAttribute('stroke', report.score >= 80 ? '#059669' : report.score >= 50 ? '#d97706' : '#dc2626');

            els.checks.innerHTML = '';
            els.checks.hidden = problems.length === 0;

            problems.forEach(function (result) {
                var li = document.createElement('li');
                li.className = 'seo-check is-' + result.status;

                var dot = document.createElement('span');
                dot.className = 'seo-check-dot';

                var text = document.createElement('span');
                text.textContent = result.message;

                li.appendChild(dot);
                li.appendChild(text);
                els.checks.appendChild(li);
            });
        }

        function scheduleAnalyze() {
            if (analyzeTimer) clearTimeout(analyzeTimer);

            analyzeTimer = setTimeout(function () {
                request(ANALYZE_URL, {
                    method: 'POST',
                    body: JSON.stringify({
                        content: [draft.title, draft.description].filter(Boolean).join('\n\n'),
                        keyword: draft.focusKeyword || undefined,
                        title: draft.title || undefined,
                        description: draft.description || undefined,
                        locale: LOCALE || undefined,
                    }),
                }).then(renderScore).catch(function () {
                    // A failed background score must not block editing.
                });
            }, 600);
        }

        function load() {
            request(withQuery(DATA_URL)).then(function (data) {
                stored = data.stored || {};
                draft = Object.assign({}, stored);

                els.title.value = draft.title || '';
                els.description.value = draft.description || '';
                els.keyword.value = draft.focusKeyword || '';

                syncHints();
                syncButtons();
                clearStatus();
            }).catch(function (e) {
                showError(e.message);
            });
        }

        els.title.addEventListener('input', function () {
            draft.title = this.value;
            syncHints();
            syncButtons();
            scheduleAnalyze();
        });

        els.description.addEventListener('input', function () {
            draft.description = this.value;
            syncHints();
            syncButtons();
            scheduleAnalyze();
        });

        els.keyword.addEventListener('input', function () {
            draft.focusKeyword = this.value;
            syncButtons();
            scheduleAnalyze();
        });

        els.reset.addEventListener('click', function () {
            draft = Object.assign({}, stored);
            els.title.value = draft.title || '';
            els.description.value = draft.description || '';
            els.keyword.value = draft.focusKeyword || '';
            syncHints();
            syncButtons();
        });

        els.save.addEventListener('click', function () {
            els.save.disabled = true;
            els.save.textContent = 'Đang lưu…';

            request(DATA_URL, {
                method: 'PUT',
                body: JSON.stringify(Object.assign({}, draft, { locale: LOCALE || undefined })),
            }).then(function (response) {
                stored = Object.assign({}, draft);
                clearStatus();
                showWarnings(response && response.warnings);
            }).catch(function (e) {
                showError(e.message);
            }).finally(function () {
                els.save.textContent = 'Lưu';
                syncButtons();
            });
        });

        load();
    })();
    </script>
@endpush
