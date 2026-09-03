import { useEffect } from 'react'
import type { AnalysisReport, CheckResult, MetaStoreTarget, SeoClient } from '@duxbo/seo-core'
import { useMetaStore } from './useMetaStore.js'

export interface SeoPanelProps {
  client: SeoClient
  target: MetaStoreTarget
  /**
   * The page's body content, for the live score. Omit to edit metadata
   * without analysis — a title/description-only form still works.
   */
  content?: string
  descriptionMin?: number
  descriptionMax?: number
  onSaved?: () => void
  className?: string
}

/**
 * A working meta editor: title, description, focus keyword, and a live score
 * once `content` is supplied. Built on `@duxbo/seo-core`'s `MetaStore`, so
 * loading, saving and debounced analysis are already handled — this component
 * is markup and Tailwind utility classes over state the hook already tracks.
 *
 * Requires Tailwind configured in the host project, with this package's
 * output included in its content globs:
 *
 *     content: ['./node_modules/@duxbo/seo-react/dist/*.js', …]
 *
 * Without that, every class here is purged and the panel renders unstyled.
 */
export function SeoPanel({
  client,
  target,
  content,
  descriptionMin = 120,
  descriptionMax = 158,
  onSaved,
  className = '',
}: SeoPanelProps) {
  const store = useMetaStore(client, target)

  useEffect(() => {
    store.load()
    // Re-runs only when the record identity changes; useMetaStore already
    // rebuilds the store in that case, and this kicks off its first load.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [target.type, target.id, target.locale])

  useEffect(() => {
    if (content !== undefined) store.analyze(content)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [content, store.draft.title, store.draft.description, store.draft.focusKeyword])

  async function handleSave() {
    await store.save()
    if (!store.error) onSaved?.()
  }

  const descLength = store.draft.description?.length ?? 0
  const descInRange = descLength >= descriptionMin && descLength <= descriptionMax

  return (
    <div className={`space-y-5 text-sm text-slate-900 ${className}`}>
      {store.isLoading && (
        <p className="text-slate-500" role="status">
          Đang tải…
        </p>
      )}

      {store.error && (
        <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700" role="alert">
          {store.error.message}
        </p>
      )}

      <Field label="Tiêu đề">
        <input
          type="text"
          value={store.draft.title ?? ''}
          onChange={(e) => store.set('title', e.target.value)}
          className="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
          placeholder="Để trống dùng giá trị mặc định"
        />
        <p className="mt-1 text-xs text-slate-400">{(store.draft.title ?? '').length} ký tự</p>
      </Field>

      <Field label="Mô tả">
        <textarea
          value={store.draft.description ?? ''}
          onChange={(e) => store.set('description', e.target.value)}
          rows={3}
          className="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
        />
        <p className={`mt-1 text-xs ${descInRange ? 'text-emerald-600' : 'text-amber-600'}`}>
          {descLength} ký tự (khuyến nghị {descriptionMin}–{descriptionMax})
        </p>
      </Field>

      <Field label="Từ khoá chính">
        <input
          type="text"
          value={store.draft.focusKeyword ?? ''}
          onChange={(e) => store.set('focusKeyword', e.target.value)}
          className="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
        />
      </Field>

      {content !== undefined && <ScorePanel report={store.report} loading={store.isAnalyzing} />}

      <div className="flex items-center gap-3 border-t border-slate-100 pt-4">
        <button
          type="button"
          onClick={handleSave}
          disabled={!store.isDirty || store.isSaving}
          className="rounded-md bg-slate-900 px-4 py-2 font-medium text-white disabled:cursor-not-allowed disabled:bg-slate-300"
        >
          {store.isSaving ? 'Đang lưu…' : 'Lưu'}
        </button>

        <button
          type="button"
          onClick={() => store.reset()}
          disabled={!store.isDirty}
          className="rounded-md border border-slate-300 px-4 py-2 font-medium text-slate-700 disabled:cursor-not-allowed disabled:text-slate-300"
        >
          Hoàn tác
        </button>

        {store.isDirty && <span className="text-xs text-amber-600">Có thay đổi chưa lưu</span>}
      </div>
    </div>
  )
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1 block font-medium text-slate-700">{label}</span>
      {children}
    </label>
  )
}

function ScorePanel({ report, loading }: { report: AnalysisReport | null; loading: boolean }) {
  if (report === null) {
    return <p className="text-xs text-slate-400">{loading ? 'Đang phân tích…' : 'Chưa có điểm phân tích.'}</p>
  }

  const problems = report.results.filter((r) => r.status === 'fail' || r.status === 'warning')

  return (
    <div className="rounded-md border border-slate-200 p-4">
      <div className="flex items-center gap-3">
        <ScoreRing score={report.score} />
        <div>
          <p className="font-medium text-slate-900">{report.score}/100</p>
          <p className="text-xs text-slate-500">
            {loading ? 'Đang cập nhật…' : `${problems.length} điểm cần chú ý`}
          </p>
        </div>
      </div>

      {problems.length > 0 && (
        <ul className="mt-3 space-y-1.5 border-t border-slate-100 pt-3">
          {problems.map((result) => (
            <CheckRow key={result.id} result={result} />
          ))}
        </ul>
      )}
    </div>
  )
}

function CheckRow({ result }: { result: CheckResult }) {
  const color = result.status === 'fail' ? 'text-red-600' : 'text-amber-600'
  const dot = result.status === 'fail' ? 'bg-red-500' : 'bg-amber-500'

  return (
    <li className="flex items-start gap-2 text-xs">
      <span className={`mt-1 h-1.5 w-1.5 shrink-0 rounded-full ${dot}`} />
      <span className={color}>{result.message}</span>
    </li>
  )
}

function ScoreRing({ score }: { score: number }) {
  const color = score >= 80 ? '#059669' : score >= 50 ? '#d97706' : '#dc2626'
  const circumference = 2 * Math.PI * 16
  const offset = circumference - (score / 100) * circumference

  return (
    <svg width="40" height="40" viewBox="0 0 40 40" className="shrink-0">
      <circle cx="20" cy="20" r="16" fill="none" stroke="#e2e8f0" strokeWidth="4" />
      <circle
        cx="20"
        cy="20"
        r="16"
        fill="none"
        stroke={color}
        strokeWidth="4"
        strokeDasharray={circumference}
        strokeDashoffset={offset}
        strokeLinecap="round"
        transform="rotate(-90 20 20)"
      />
    </svg>
  )
}
