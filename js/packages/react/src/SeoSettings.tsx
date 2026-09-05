import { useEffect, useState } from 'react'
import type { DynamicSettingsResponse, SeoClient, SettingsResponse } from '@duxbo/seo-core'

export interface SeoSettingsProps {
  client: SeoClient
  className?: string
}

const BOOLEAN_KEYS = ['enabled', 'robots.block_ai_crawlers', 'indexnow.enabled', 'search_console.enabled']
const ARRAY_KEYS = ['schema.organization.sameAs']

/**
 * The top half is read-only status — what a deploy actually set, including
 * the demo-domain master switch. The bottom half, when dynamic settings are
 * enabled, is an editable form over the same allowlisted keys
 * `DynamicSettingsController` exposes — saved immediately, no deploy.
 */
export function SeoSettings({ client, className = '' }: SeoSettingsProps) {
  const [settings, setSettings] = useState<SettingsResponse | null>(null)
  const [dynamic, setDynamic] = useState<DynamicSettingsResponse | null>(null)
  const [draft, setDraft] = useState<Record<string, string | boolean>>({})
  const [status, setStatus] = useState<{ kind: 'ok' | 'error'; message: string } | null>(null)
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    client.settings().then(setSettings)
    client.dynamicSettings().then((data) => {
      setDynamic(data)
      setDraft(draftFrom(data))
    })
  }, [client])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSaving(true)
    setStatus(null)

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

      // A blank secret field means "leave it": never send it, since an
      // empty string would be written as the new value and clear it instead.
      const original = dynamic?.settings[key]
      const isSecret = original !== undefined && original.secret
      if (isSecret && value === '') continue

      payload[key] = value
    }

    try {
      await client.updateDynamicSettings(payload)
      const fresh = await client.dynamicSettings()
      setDynamic(fresh)
      setDraft(draftFrom(fresh))
      setStatus({ kind: 'ok', message: 'Đã lưu cấu hình.' })
    } catch (e) {
      setStatus({ kind: 'error', message: e instanceof Error ? e.message : String(e) })
    } finally {
      setSaving(false)
    }
  }

  function set(key: string, value: string | boolean) {
    setDraft((d) => ({ ...d, [key]: value }))
  }

  if (!settings) {
    return (
      <p className={`text-sm text-slate-500 ${className}`} role="status">
        Đang tải…
      </p>
    )
  }

  return (
    <div className={`space-y-5 text-sm text-slate-900 ${className}`}>
      <Section title="Chỉ mục hoá">
        <Row label="Master switch (seo.enabled)">
          {settings.seoEnabled ? <Pill tone="ok">Bật</Pill> : <Pill tone="bad">Tắt — noindex toàn site</Pill>}
        </Row>
        <Row label="Môi trường hiện tại">
          <span className="font-mono text-xs">{settings.currentEnvironment}</span>
        </Row>
        <Row label="Môi trường cho index">
          <span className="font-mono text-xs">{settings.indexableEnvironments.join(', ') || '—'}</span>
        </Row>
        <Row label="Locale hỗ trợ">
          <span className="font-mono text-xs">{settings.supportedLocales.join(', ') || '(1 ngôn ngữ)'}</span>
        </Row>
      </Section>

      <Section title="Bề mặt truy cập">
        <Row label="REST API (/api/seo/v1)">
          <Pill tone={settings.apiEnabled ? 'ok' : 'neutral'}>{settings.apiEnabled ? 'Bật' : 'Tắt'}</Pill>
        </Row>
        <Row label="Panel">
          <Pill tone={settings.panelEnabled ? 'ok' : 'neutral'}>{settings.panelEnabled ? 'Bật' : 'Tắt'}</Pill>
        </Row>
        <Row label="Model được expose">
          <span className="font-mono text-xs">{settings.exposedModels.join(', ') || 'Chưa có'}</span>
        </Row>
        <Row label="Host redirect/canonical được phép">
          <span className="font-mono text-xs">{settings.allowedHosts.join(', ') || '(chỉ domain của app)'}</span>
        </Row>
      </Section>

      <Section title="Sitemap & AI">
        <Row label="Nguồn sitemap đã đăng ký">{settings.sitemapSourceCount}</Row>
        <Row label="AI driver mặc định">
          <span className="font-mono text-xs">{settings.aiDriver}</span>
        </Row>
        <Row label="Ngân sách token AI/ngày">
          {settings.aiBudget > 0 ? settings.aiBudget.toLocaleString('vi-VN') : 'Không giới hạn'}
        </Row>
        <Row label="Rate limit /analyze">
          <span className="font-mono text-xs">{settings.analysisRateLimit}</span>
        </Row>
      </Section>

      {dynamic && !dynamic.enabled && (
        <div className="rounded-md border border-slate-200 p-4 text-slate-500">
          Cấu hình động đang tắt. Bật <code>seo.settings.enabled</code> trong <code>config/seo.php</code> để chỉnh
          các mục dưới đây ngay tại trang này, không cần deploy lại.
        </div>
      )}

      {dynamic?.enabled && (
        <form onSubmit={handleSubmit} className="space-y-5 rounded-md border border-slate-200 p-4">
          <div>
            <h3 className="font-medium text-slate-900">Chỉnh cấu hình</h3>
            <p className="mt-1 text-xs text-slate-500">
              Lưu ở đây ghi đè config/seo.php ngay lập tức. Để trống một ô rồi lưu để quay lại giá trị mặc định.
            </p>
          </div>

          {status && (
            <p
              className={
                status.kind === 'ok'
                  ? 'rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-700'
                  : 'rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700'
              }
              role={status.kind === 'error' ? 'alert' : 'status'}
            >
              {status.message}
            </p>
          )}

          <FormGroup title="Chung">
            <Checkbox label="SEO đang bật cho toàn site" checked={Boolean(draft.enabled)} onChange={(v) => set('enabled', v)} />
            <Field label="Tên site" value={String(draft.site_name ?? '')} onChange={(v) => set('site_name', v)} />
          </FormGroup>

          <FormGroup title="Meta mặc định">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Field
                label="Tiêu đề mặc định"
                value={String(draft['defaults.title'] ?? '')}
                onChange={(v) => set('defaults.title', v)}
              />
              <Field
                label="Robots mặc định"
                value={String(draft['defaults.robots'] ?? '')}
                onChange={(v) => set('defaults.robots', v)}
                placeholder="max-image-preview:large"
              />
            </div>
            <Field
              label="Mô tả mặc định"
              textarea
              rows={2}
              value={String(draft['defaults.description'] ?? '')}
              onChange={(v) => set('defaults.description', v)}
            />
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Field
                label="og:site_name mặc định"
                value={String(draft['defaults.og.site_name'] ?? '')}
                onChange={(v) => set('defaults.og.site_name', v)}
              />
              <SelectField
                label="twitter:card mặc định"
                value={String(draft['defaults.twitter.card'] ?? 'summary_large_image')}
                onChange={(v) => set('defaults.twitter.card', v)}
                options={['summary', 'summary_large_image']}
              />
            </div>
          </FormGroup>

          <FormGroup title="Xác minh Search Console / Webmaster">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Field label="Google" value={String(draft['verification.google'] ?? '')} onChange={(v) => set('verification.google', v)} />
              <Field label="Bing" value={String(draft['verification.bing'] ?? '')} onChange={(v) => set('verification.bing', v)} />
              <Field label="Yandex" value={String(draft['verification.yandex'] ?? '')} onChange={(v) => set('verification.yandex', v)} />
              <Field
                label="Pinterest"
                value={String(draft['verification.pinterest'] ?? '')}
                onChange={(v) => set('verification.pinterest', v)}
              />
              <Field
                label="Facebook"
                value={String(draft['verification.facebook'] ?? '')}
                onChange={(v) => set('verification.facebook', v)}
              />
            </div>
          </FormGroup>

          <FormGroup title="Robots & Schema.org">
            <Checkbox
              label="Chặn bot huấn luyện AI (GPTBot, ClaudeBot…) trong robots.txt — không ảnh hưởng Googlebot/Bingbot"
              checked={Boolean(draft['robots.block_ai_crawlers'])}
              onChange={(v) => set('robots.block_ai_crawlers', v)}
            />
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Field
                label="Tên tổ chức (Organization)"
                value={String(draft['schema.organization.name'] ?? '')}
                onChange={(v) => set('schema.organization.name', v)}
              />
              <Field
                label="Logo (URL)"
                value={String(draft['schema.organization.logo'] ?? '')}
                onChange={(v) => set('schema.organization.logo', v)}
              />
            </div>
            <Field
              label="Mạng xã hội (sameAs — mỗi dòng một URL)"
              textarea
              rows={3}
              value={String(draft['schema.organization.sameAs'] ?? '')}
              onChange={(v) => set('schema.organization.sameAs', v)}
              placeholder={'https://facebook.com/...\nhttps://youtube.com/...'}
            />
            <Field
              label="URL tìm kiếm nội bộ (sitelinks search box)"
              value={String(draft['schema.website.search_url'] ?? '')}
              onChange={(v) => set('schema.website.search_url', v)}
              placeholder="/tim-kiem?q={search_term_string}"
            />
          </FormGroup>

          <FormGroup title="IndexNow">
            <Checkbox
              label="Bật gửi IndexNow (Bing, Yandex, Seznam)"
              checked={Boolean(draft['indexnow.enabled'])}
              onChange={(v) => set('indexnow.enabled', v)}
            />
            <Field
              label="Key"
              mono
              value={String(draft['indexnow.key'] ?? '')}
              onChange={(v) => set('indexnow.key', v)}
            />
          </FormGroup>

          <FormGroup title="Search Console API">
            <Checkbox
              label="Bật đồng bộ số liệu Search Console"
              checked={Boolean(draft['search_console.enabled'])}
              onChange={(v) => set('search_console.enabled', v)}
            />
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Field
                label="Client ID"
                value={String(draft['search_console.client_id'] ?? '')}
                onChange={(v) => set('search_console.client_id', v)}
              />
              <Field
                label="Site URL"
                value={String(draft['search_console.site_url'] ?? '')}
                onChange={(v) => set('search_console.site_url', v)}
                placeholder="https://trangcuatoi.vn/"
              />
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <SecretField
                label="Client Secret"
                isSet={secretIsSet(dynamic, 'search_console.client_secret')}
                value={String(draft['search_console.client_secret'] ?? '')}
                onChange={(v) => set('search_console.client_secret', v)}
              />
              <SecretField
                label="Refresh Token"
                isSet={secretIsSet(dynamic, 'search_console.refresh_token')}
                value={String(draft['search_console.refresh_token'] ?? '')}
                onChange={(v) => set('search_console.refresh_token', v)}
              />
            </div>
          </FormGroup>

          <div className="border-t border-slate-100 pt-4">
            <button
              type="submit"
              disabled={saving}
              className="rounded-md bg-slate-900 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-300"
            >
              {saving ? 'Đang lưu…' : 'Lưu cấu hình'}
            </button>
          </div>
        </form>
      )}
    </div>
  )
}

function draftFrom(data: DynamicSettingsResponse): Record<string, string | boolean> {
  const draft: Record<string, string | boolean> = {}

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

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-md border border-slate-200 p-4">
      <h3 className="mb-3 font-medium text-slate-900">{title}</h3>
      <dl className="divide-y divide-slate-100">{children}</dl>
    </div>
  )
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2">
      <dt className="text-slate-500">{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}

function Pill({ tone, children }: { tone: 'ok' | 'bad' | 'neutral'; children: React.ReactNode }) {
  const classes = {
    ok: 'bg-emerald-50 text-emerald-700',
    bad: 'bg-red-50 text-red-700',
    neutral: 'bg-slate-100 text-slate-600',
  }[tone]

  return <span className={`rounded-full px-2 py-0.5 text-xs ${classes}`}>{children}</span>
}

function FormGroup({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="space-y-3">
      <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
      {children}
    </div>
  )
}

function Field({
  label,
  value,
  onChange,
  placeholder,
  textarea = false,
  rows = 3,
  mono = false,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  placeholder?: string
  textarea?: boolean
  rows?: number
  mono?: boolean
}) {
  const inputClass = `w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500 ${mono ? 'font-mono' : ''}`

  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-slate-700">{label}</span>
      {textarea ? (
        <textarea
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          rows={rows}
          className={inputClass}
        />
      ) : (
        <input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          className={inputClass}
        />
      )}
    </label>
  )
}

function SelectField({
  label,
  value,
  onChange,
  options,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  options: string[]
}) {
  return (
    <label className="block">
      <span className="mb-1 block text-xs font-medium text-slate-700">{label}</span>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
      >
        {options.map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </select>
    </label>
  )
}

function Checkbox({
  label,
  checked,
  onChange,
}: {
  label: string
  checked: boolean
  onChange: (value: boolean) => void
}) {
  return (
    <label className="flex items-center gap-2 text-sm">
      <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} className="h-4 w-4" />
      {label}
    </label>
  )
}

function SecretField({
  label,
  isSet,
  value,
  onChange,
}: {
  label: string
  isSet: boolean
  value: string
  onChange: (value: string) => void
}) {
  return (
    <label className="block">
      <span className="mb-1 flex items-center gap-2 text-xs font-medium text-slate-700">
        {label}
        {isSet ? (
          <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">Đã đặt</span>
        ) : (
          <span className="rounded-full bg-slate-100 px-2 py-0.5 text-slate-600">Chưa đặt</span>
        )}
      </span>
      <input
        type="password"
        autoComplete="new-password"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder="Để trống = giữ nguyên"
        className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
      />
    </label>
  )
}
