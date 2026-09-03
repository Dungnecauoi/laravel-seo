import { defineComponent, h, type PropType, ref } from 'vue'
import type { SeoClient, SettingsResponse } from '@duxbo/seo-core'

/**
 * Read-only. What is actually in effect — including the demo-domain master
 * switch — without a way to write `config/seo.php` back over HTTP.
 */
export const SeoSettings = defineComponent({
  name: 'SeoSettings',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const settings = ref<SettingsResponse | null>(null)

    props.client.settings().then((data) => {
      settings.value = data
    })

    return () => {
      const s = settings.value

      if (!s) {
        return h('p', { class: 'text-sm text-slate-500', role: 'status' }, 'Đang tải…')
      }

      return h('div', { class: 'space-y-5 text-sm text-slate-900' }, [
        section('Chỉ mục hoá', [
          row('Master switch (seo.enabled)', s.seoEnabled ? pill('ok', 'Bật') : pill('bad', 'Tắt — noindex toàn site')),
          row('Môi trường hiện tại', h('span', { class: 'font-mono text-xs' }, s.currentEnvironment)),
          row('Môi trường cho index', h('span', { class: 'font-mono text-xs' }, s.indexableEnvironments.join(', ') || '—')),
          row('Locale hỗ trợ', h('span', { class: 'font-mono text-xs' }, s.supportedLocales.join(', ') || '(1 ngôn ngữ)')),
        ]),

        section('Bề mặt truy cập', [
          row('REST API (/api/seo/v1)', pill(s.apiEnabled ? 'ok' : 'neutral', s.apiEnabled ? 'Bật' : 'Tắt')),
          row('Panel', pill(s.panelEnabled ? 'ok' : 'neutral', s.panelEnabled ? 'Bật' : 'Tắt')),
          row('Model được expose', h('span', { class: 'font-mono text-xs' }, s.exposedModels.join(', ') || 'Chưa có')),
          row(
            'Host redirect/canonical được phép',
            h('span', { class: 'font-mono text-xs' }, s.allowedHosts.join(', ') || '(chỉ domain của app)'),
          ),
        ]),

        section('Sitemap & AI', [
          row('Nguồn sitemap đã đăng ký', s.sitemapSourceCount),
          row('AI driver mặc định', h('span', { class: 'font-mono text-xs' }, s.aiDriver)),
          row('Ngân sách token AI/ngày', s.aiBudget > 0 ? s.aiBudget.toLocaleString('vi-VN') : 'Không giới hạn'),
          row('Rate limit /analyze', h('span', { class: 'font-mono text-xs' }, s.analysisRateLimit)),
        ]),
      ])
    }
  },
})

function section(title: string, rows: ReturnType<typeof h>[]) {
  return h('div', { class: 'rounded-md border border-slate-200 p-4' }, [
    h('h3', { class: 'mb-3 font-medium text-slate-900' }, title),
    h('dl', { class: 'divide-y divide-slate-100' }, rows),
  ])
}

function row(label: string, value: string | number | ReturnType<typeof h>) {
  return h('div', { class: 'flex items-center justify-between gap-4 py-2' }, [
    h('dt', { class: 'text-slate-500' }, label),
    h('dd', {}, typeof value === 'number' ? String(value) : value),
  ])
}

function pill(tone: 'ok' | 'bad' | 'neutral', text: string) {
  const classes = {
    ok: 'bg-emerald-50 text-emerald-700',
    bad: 'bg-red-50 text-red-700',
    neutral: 'bg-slate-100 text-slate-600',
  }[tone]

  return h('span', { class: `rounded-full px-2 py-0.5 text-xs ${classes}` }, text)
}
