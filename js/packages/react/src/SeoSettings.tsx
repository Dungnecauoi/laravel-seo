import { useEffect, useState } from 'react'
import type { SeoClient, SettingsResponse } from '@duxbo/seo-core'

export interface SeoSettingsProps {
  client: SeoClient
  className?: string
}

/**
 * Read-only. What is actually in effect — including the demo-domain master
 * switch — without a way to write `config/seo.php` back over HTTP.
 */
export function SeoSettings({ client, className = '' }: SeoSettingsProps) {
  const [settings, setSettings] = useState<SettingsResponse | null>(null)

  useEffect(() => {
    client.settings().then(setSettings)
  }, [client])

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
    </div>
  )
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
