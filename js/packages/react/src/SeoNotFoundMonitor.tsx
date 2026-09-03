import { useEffect, useState } from 'react'
import { SeoApiError } from '@duxbo/seo-core'
import type { NotFoundEntry, SeoClient } from '@duxbo/seo-core'

export interface SeoNotFoundMonitorProps {
  client: SeoClient
  className?: string
}

/**
 * The 404 log: prune stale entries, or turn one hit straight into a
 * redirect. `path`, `referrer` and `user_agent` on `NotFoundEntry` are
 * already HTML-escaped by the API — this is attacker-supplied text, and the
 * API escapes it so any consumer is safe by default, not just one that
 * happens to text-render it. Rendered here via `dangerouslySetInnerHTML` for
 * that reason: text-rendering an already-escaped string would show the
 * literal entities (`&lt;script&gt;`) instead of the path Google actually
 * requested.
 */
export function SeoNotFoundMonitor({ client, className = '' }: SeoNotFoundMonitorProps) {
  const [entries, setEntries] = useState<NotFoundEntry[] | null>(null)
  const [days, setDays] = useState(90)
  const [targets, setTargets] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)

  function reload() {
    client.notFound().then(setEntries)
  }

  useEffect(reload, [client])

  async function prune(e: React.FormEvent) {
    e.preventDefault()
    await client.pruneNotFound(days)
    reload()
  }

  async function convertToRedirect(id: number) {
    const target = targets[id]

    if (!target) return

    setError(null)

    try {
      await client.convertNotFoundToRedirect(id, target)
      reload()
    } catch (e) {
      setError(e instanceof SeoApiError ? e.fieldError('target') : e instanceof Error ? e.message : String(e))
    }
  }

  async function remove(id: number) {
    if (!window.confirm('Xoá dòng này?')) return
    await client.deleteNotFound(id)
    reload()
  }

  return (
    <div className={`space-y-4 text-sm text-slate-900 ${className}`}>
      {error && (
        <p className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700" role="alert">
          {error}
        </p>
      )}

      <form
        onSubmit={prune}
        className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 p-3"
      >
        <span className="text-xs text-slate-500">Bot quét thường tạo hàng loạt dòng vô nghĩa — dọn định kỳ.</span>
        <div className="flex items-center gap-2">
          <input
            type="number"
            min={1}
            value={days}
            onChange={(e) => setDays(Number(e.target.value))}
            className="w-20 rounded-md border border-slate-300 px-2 py-1 text-sm"
          />
          <button type="submit" className="rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700">
            Xoá mục cũ hơn (ngày)
          </button>
        </div>
      </form>

      {entries === null ? (
        <p className="text-slate-500" role="status">
          Đang tải…
        </p>
      ) : entries.length === 0 ? (
        <div className="rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400">
          Không có link 404 nào được ghi nhận.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-md border border-slate-200">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
                <th className="px-3 py-2 font-medium">Đường dẫn</th>
                <th className="px-3 py-2 font-medium">Lượt</th>
                <th className="px-3 py-2 font-medium">Thấy lần cuối</th>
                <th className="px-3 py-2" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {entries.map((row) => (
                <tr key={row.id}>
                  <td className="px-3 py-2 font-mono text-xs" dangerouslySetInnerHTML={{ __html: row.path }} />
                  <td className="px-3 py-2">{row.hits.toLocaleString('vi-VN')}</td>
                  <td className="px-3 py-2 text-slate-500">{row.last_seen_at}</td>
                  <td className="whitespace-nowrap px-3 py-2 text-right">
                    <input
                      type="text"
                      placeholder="Chuyển tới…"
                      value={targets[row.id] ?? ''}
                      onChange={(e) => setTargets((t) => ({ ...t, [row.id]: e.target.value }))}
                      className="mr-2 w-36 rounded-md border border-slate-300 px-2 py-1 text-xs"
                    />
                    <button
                      type="button"
                      onClick={() => convertToRedirect(row.id)}
                      className="mr-2 rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700"
                    >
                      Tạo redirect
                    </button>
                    <button
                      type="button"
                      onClick={() => remove(row.id)}
                      className="rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700"
                    >
                      Xoá
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
