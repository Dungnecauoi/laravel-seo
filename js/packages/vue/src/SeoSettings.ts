import { defineComponent, h, type PropType, reactive, ref } from 'vue'
import type { DynamicSettingsResponse, SeoClient, SettingsResponse } from '@duxbo/seo-core'

const BOOLEAN_KEYS = ['enabled', 'robots.block_ai_crawlers', 'indexnow.enabled', 'search_console.enabled']
const ARRAY_KEYS = ['schema.organization.sameAs']

type Draft = Record<string, string | boolean>

/**
 * The top half is read-only status — what a deploy actually set, including
 * the demo-domain master switch. The bottom half, when dynamic settings are
 * enabled, is an editable form over the same allowlisted keys
 * `DynamicSettingsController` exposes — saved immediately, no deploy.
 */
export const SeoSettings = defineComponent({
  name: 'SeoSettings',

  props: {
    client: { type: Object as PropType<SeoClient>, required: true },
  },

  setup(props) {
    const settings = ref<SettingsResponse | null>(null)
    const dynamic = ref<DynamicSettingsResponse | null>(null)
    const draft = reactive<Draft>({})
    const status = ref<{ kind: 'ok' | 'error'; message: string } | null>(null)
    const saving = ref(false)

    props.client.settings().then((data) => {
      settings.value = data
    })

    function loadDynamic() {
      props.client.dynamicSettings().then((data) => {
        dynamic.value = data
        Object.keys(draft).forEach((key) => delete draft[key])
        Object.assign(draft, draftFrom(data))
      })
    }

    loadDynamic()

    function set(key: string, value: string | boolean) {
      draft[key] = value
    }

    async function handleSubmit() {
      saving.value = true
      status.value = null

      const payload: Record<string, unknown> = {}

      for (const [key, value] of Object.entries(draft)) {
        if (ARRAY_KEYS.includes(key)) {
          payload[key] = String(value)
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)

          continue
        }

        if (BOOLEAN_KEYS.includes(key)) {
          payload[key] = Boolean(value)
          continue
        }

        const original = dynamic.value?.settings[key]
        const isSecret = original !== undefined && original.secret
        if (isSecret && value === '') continue

        payload[key] = value
      }

      try {
        await props.client.updateDynamicSettings(payload)
        loadDynamic()
        status.value = { kind: 'ok', message: 'Đã lưu cấu hình.' }
      } catch (e) {
        status.value = { kind: 'error', message: e instanceof Error ? e.message : String(e) }
      } finally {
        saving.value = false
      }
    }

    return () => {
      const s = settings.value

      if (!s) {
        return h('p', { class: 'text-sm text-slate-500', role: 'status' }, 'Đang tải…')
      }

      const d = dynamic.value

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

        d && !d.enabled &&
          h('div', { class: 'rounded-md border border-slate-200 p-4 text-slate-500' }, [
            'Cấu hình động đang tắt. Bật ',
            h('code', 'seo.settings.enabled'),
            ' trong ',
            h('code', 'config/seo.php'),
            ' để chỉnh các mục dưới đây ngay tại trang này, không cần deploy lại.',
          ]),

        d?.enabled &&
          h(
            'form',
            {
              onSubmit: (e: Event) => {
                e.preventDefault()
                void handleSubmit()
              },
              class: 'space-y-5 rounded-md border border-slate-200 p-4',
            },
            [
              h('div', [
                h('h3', { class: 'font-medium text-slate-900' }, 'Chỉnh cấu hình'),
                h(
                  'p',
                  { class: 'mt-1 text-xs text-slate-500' },
                  'Lưu ở đây ghi đè config/seo.php ngay lập tức. Để trống một ô rồi lưu để quay lại giá trị mặc định.',
                ),
              ]),

              status.value &&
                h(
                  'p',
                  {
                    class:
                      status.value.kind === 'ok'
                        ? 'rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-700'
                        : 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700',
                    role: status.value.kind === 'error' ? 'alert' : 'status',
                  },
                  status.value.message,
                ),

              formGroup('Chung', [
                checkbox('SEO đang bật cho toàn site', Boolean(draft.enabled), (v) => set('enabled', v)),
                field('Tên site', String(draft.site_name ?? ''), (v) => set('site_name', v)),
              ]),

              formGroup('Meta mặc định', [
                h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-2' }, [
                  field('Tiêu đề mặc định', String(draft['defaults.title'] ?? ''), (v) => set('defaults.title', v)),
                  field(
                    'Robots mặc định',
                    String(draft['defaults.robots'] ?? ''),
                    (v) => set('defaults.robots', v),
                    { placeholder: 'max-image-preview:large' },
                  ),
                ]),
                field(
                  'Mô tả mặc định',
                  String(draft['defaults.description'] ?? ''),
                  (v) => set('defaults.description', v),
                  { textarea: true, rows: 2 },
                ),
                h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-2' }, [
                  field(
                    'og:site_name mặc định',
                    String(draft['defaults.og.site_name'] ?? ''),
                    (v) => set('defaults.og.site_name', v),
                  ),
                  selectField(
                    'twitter:card mặc định',
                    String(draft['defaults.twitter.card'] ?? 'summary_large_image'),
                    (v) => set('defaults.twitter.card', v),
                    ['summary', 'summary_large_image'],
                  ),
                ]),
              ]),

              formGroup('Xác minh Search Console / Webmaster', [
                h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-2' }, [
                  field('Google', String(draft['verification.google'] ?? ''), (v) => set('verification.google', v)),
                  field('Bing', String(draft['verification.bing'] ?? ''), (v) => set('verification.bing', v)),
                  field('Yandex', String(draft['verification.yandex'] ?? ''), (v) => set('verification.yandex', v)),
                  field(
                    'Pinterest',
                    String(draft['verification.pinterest'] ?? ''),
                    (v) => set('verification.pinterest', v),
                  ),
                  field(
                    'Facebook',
                    String(draft['verification.facebook'] ?? ''),
                    (v) => set('verification.facebook', v),
                  ),
                ]),
              ]),

              formGroup('Robots & Schema.org', [
                checkbox(
                  'Chặn bot huấn luyện AI (GPTBot, ClaudeBot…) trong robots.txt — không ảnh hưởng Googlebot/Bingbot',
                  Boolean(draft['robots.block_ai_crawlers']),
                  (v) => set('robots.block_ai_crawlers', v),
                ),
                h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-2' }, [
                  field(
                    'Tên tổ chức (Organization)',
                    String(draft['schema.organization.name'] ?? ''),
                    (v) => set('schema.organization.name', v),
                  ),
                  field(
                    'Logo (URL)',
                    String(draft['schema.organization.logo'] ?? ''),
                    (v) => set('schema.organization.logo', v),
                  ),
                ]),
                field(
                  'Mạng xã hội (sameAs — mỗi dòng một URL)',
                  String(draft['schema.organization.sameAs'] ?? ''),
                  (v) => set('schema.organization.sameAs', v),
                  { textarea: true, rows: 3, placeholder: 'https://facebook.com/...\nhttps://youtube.com/...' },
                ),
                field(
                  'URL tìm kiếm nội bộ (sitelinks search box)',
                  String(draft['schema.website.search_url'] ?? ''),
                  (v) => set('schema.website.search_url', v),
                  { placeholder: '/tim-kiem?q={search_term_string}' },
                ),
              ]),

              formGroup('IndexNow', [
                checkbox(
                  'Bật gửi IndexNow (Bing, Yandex, Seznam)',
                  Boolean(draft['indexnow.enabled']),
                  (v) => set('indexnow.enabled', v),
                ),
                field('Key', String(draft['indexnow.key'] ?? ''), (v) => set('indexnow.key', v), { mono: true }),
              ]),

              formGroup('Search Console API', [
                checkbox(
                  'Bật đồng bộ số liệu Search Console',
                  Boolean(draft['search_console.enabled']),
                  (v) => set('search_console.enabled', v),
                ),
                h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-2' }, [
                  field(
                    'Client ID',
                    String(draft['search_console.client_id'] ?? ''),
                    (v) => set('search_console.client_id', v),
                  ),
                  field(
                    'Site URL',
                    String(draft['search_console.site_url'] ?? ''),
                    (v) => set('search_console.site_url', v),
                    { placeholder: 'https://trangcuatoi.vn/' },
                  ),
                ]),
                h('div', { class: 'grid grid-cols-1 gap-3 sm:grid-cols-2' }, [
                  secretField(
                    'Client Secret',
                    secretIsSet(d, 'search_console.client_secret'),
                    String(draft['search_console.client_secret'] ?? ''),
                    (v) => set('search_console.client_secret', v),
                  ),
                  secretField(
                    'Refresh Token',
                    secretIsSet(d, 'search_console.refresh_token'),
                    String(draft['search_console.refresh_token'] ?? ''),
                    (v) => set('search_console.refresh_token', v),
                  ),
                ]),
              ]),

              h('div', { class: 'border-t border-slate-100 pt-4' }, [
                h(
                  'button',
                  {
                    type: 'submit',
                    disabled: saving.value,
                    class:
                      'rounded-md bg-slate-900 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-300',
                  },
                  saving.value ? 'Đang lưu…' : 'Lưu cấu hình',
                ),
              ]),
            ],
          ),
      ])
    }
  },
})

function draftFrom(data: DynamicSettingsResponse): Draft {
  const draft: Draft = {}

  for (const [key, entry] of Object.entries(data.settings)) {
    if (entry.secret) {
      draft[key] = ''
      continue
    }

    if (BOOLEAN_KEYS.includes(key)) {
      draft[key] = Boolean(entry.value)
      continue
    }

    if (ARRAY_KEYS.includes(key)) {
      draft[key] = Array.isArray(entry.value) ? entry.value.join('\n') : ''
      continue
    }

    draft[key] = entry.value == null ? '' : String(entry.value)
  }

  return draft
}

function secretIsSet(dynamic: DynamicSettingsResponse | null, key: string): boolean {
  const entry = dynamic?.settings[key]

  return entry !== undefined && entry.secret && entry.is_set
}

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

function formGroup(title: string, children: unknown[]) {
  return h('div', { class: 'space-y-3' }, [
    h('p', { class: 'text-xs font-semibold uppercase tracking-wide text-slate-500' }, title),
    ...(children as ReturnType<typeof h>[]),
  ])
}

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500'

function field(
  label: string,
  value: string,
  onChange: (value: string) => void,
  options: { placeholder?: string; textarea?: boolean; rows?: number; mono?: boolean } = {},
) {
  const cls = options.mono ? `${inputClass} font-mono` : inputClass

  const control = options.textarea
    ? h('textarea', {
        value,
        onInput: (e: Event) => onChange((e.target as HTMLTextAreaElement).value),
        placeholder: options.placeholder,
        rows: options.rows ?? 3,
        class: cls,
      })
    : h('input', {
        type: 'text',
        value,
        onInput: (e: Event) => onChange((e.target as HTMLInputElement).value),
        placeholder: options.placeholder,
        class: cls,
      })

  return h('label', { class: 'block' }, [
    h('span', { class: 'mb-1 block text-xs font-medium text-slate-700' }, label),
    control,
  ])
}

function selectField(label: string, value: string, onChange: (value: string) => void, options: string[]) {
  return h('label', { class: 'block' }, [
    h('span', { class: 'mb-1 block text-xs font-medium text-slate-700' }, label),
    h(
      'select',
      { value, onChange: (e: Event) => onChange((e.target as HTMLSelectElement).value), class: inputClass },
      options.map((option) => h('option', { value: option }, option)),
    ),
  ])
}

function checkbox(label: string, checked: boolean, onChange: (value: boolean) => void) {
  return h('label', { class: 'flex items-center gap-2 text-sm' }, [
    h('input', {
      type: 'checkbox',
      checked,
      onChange: (e: Event) => onChange((e.target as HTMLInputElement).checked),
      class: 'h-4 w-4',
    }),
    label,
  ])
}

function secretField(label: string, isSet: boolean, value: string, onChange: (value: string) => void) {
  return h('label', { class: 'block' }, [
    h('span', { class: 'mb-1 flex items-center gap-2 text-xs font-medium text-slate-700' }, [
      label,
      isSet
        ? h('span', { class: 'rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700' }, 'Đã đặt')
        : h('span', { class: 'rounded-full bg-slate-100 px-2 py-0.5 text-slate-600' }, 'Chưa đặt'),
    ]),
    h('input', {
      type: 'password',
      autocomplete: 'new-password',
      value,
      onInput: (e: Event) => onChange((e.target as HTMLInputElement).value),
      placeholder: 'Để trống = giữ nguyên',
      class: inputClass,
    }),
  ])
}
