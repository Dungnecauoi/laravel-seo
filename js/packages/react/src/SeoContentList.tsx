import { useEffect, useState } from 'react'
import type { ContentListResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoContentListProps {
  client: SeoClient
  /** Defaults to the first exposed type the API reports. */
  type?: string
  /** Called when the user picks "Sửa" on a row — the host app owns navigation. */
  onEdit?: (type: string, id: string | number) => void
  className?: string
}

/**
 * Every record of one exposed type with its title resolved through the same
 * fallback chain a real page render uses — so an untitled record shows
 * exactly what search engines see, not just what was saved.
 */
export function SeoContentList({ client, type, onEdit, className = '' }: SeoContentListProps) {
  const [activeType, setActiveType] = useState(type)
  const [page, setPage] = useState(1)
  const [response, setResponse] = useState<ContentListResponse | null>(null)
  const [error, setError] = useState<Error | null>(null)

  useEffect(() => {
    setActiveType(type)
  }, [type])

  useEffect(() => {
    let cancelled = false

    client
      .content(activeType, page)
      .then((data) => {
        if (cancelled) return
        setResponse(data)
        // The API falls back to its first exposed type when none was asked
        // for — mirror that locally so the tabs below highlight correctly.
        if (activeType === undefined && data.type) setActiveType(data.type)
      })
      .catch((e: unknown) => {
        if (!cancelled) setError(e instanceof Error ? e : new Error(String(e)))
      })

    return () => {
      cancelled = true
    }
  }, [client, activeType, page])

  function selectType(next: string) {
    setActiveType(next)
    setPage(1)
  }

  if (error) {
    return (
      <p className={`rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 ${className}`} role="alert">
        {error.message}
      </p>
    )
  }

  if (!response) {
    return (
      <p className={`text-sm text-slate-500 ${className}`} role="status">
        Đang tải…
      </p>
    )
  }

  return (
    <div className={`space-y-4 text-sm text-slate-900 ${className}`}>
      {response.exposedTypes.length > 1 && (
        <div className="flex flex-wrap gap-2">
          {response.exposedTypes.map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => selectType(t)}
              className={
                t === response.type
                  ? 'rounded-md bg-slate-900 px-3 py-1 text-xs font-medium text-white'
                  : 'rounded-md border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700'
              }
            >
              {t}
            </button>
          ))}
        </div>
      )}

      {response.type === null ? (
        <div className="rounded-md border border-slate-200 p-4 text-slate-500">
          Chưa có model nào được expose. Thêm vào <code>seo.api.models</code> trong <code>config/seo.php</code>.
        </div>
      ) : response.data.length === 0 ? (
        <div className="rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400">
          Không có bản ghi nào.
        </div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-md border border-slate-200">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
                  <th className="px-3 py-2 font-medium">Tiêu đề</th>
                  <th className="px-3 py-2 font-medium">Mô tả</th>
                  <th className="px-3 py-2 font-medium">Robots</th>
                  <th className="px-3 py-2" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {response.data.map((row) => (
                  <tr key={row.id}>
                    <td className="px-3 py-2">
                      {row.title || (
                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">
                          Chưa có tiêu đề
                        </span>
                      )}
                      <div className="mt-0.5 font-mono text-[11px] text-slate-400">{row.url}</div>
                    </td>
                    <td className="px-3 py-2 text-slate-500">
                      {row.description ? truncate(row.description, 80) : '—'}
                    </td>
                    <td className="px-3 py-2">
                      {row.robots ? (
                        <span
                          className={
                            row.robots.includes('noindex')
                              ? 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700'
                              : 'rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600'
                          }
                        >
                          {row.robots}
                        </span>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                    <td className="px-3 py-2 text-right">
                      {onEdit && response.type && (
                        <button
                          type="button"
                          onClick={() => onEdit(response.type as string, row.id)}
                          className="rounded-md border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700"
                        >
                          Sửa
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {response.meta && response.meta.lastPage > 1 && (
            <div className="flex items-center gap-3 text-xs text-slate-500">
              <button
                type="button"
                disabled={page <= 1}
                onClick={() => setPage((p) => p - 1)}
                className="rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
              >
                ← Trước
              </button>
              <span>
                Trang {response.meta.currentPage}/{response.meta.lastPage} ({response.meta.total.toLocaleString('vi-VN')}{' '}
                bản ghi)
              </span>
              <button
                type="button"
                disabled={page >= response.meta.lastPage}
                onClick={() => setPage((p) => p + 1)}
                className="rounded-md border border-slate-300 px-2 py-1 font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
              >
                Sau →
              </button>
            </div>
          )}
        </>
      )}
    </div>
  )
}

function truncate(text: string, length: number): string {
  return text.length > length ? `${text.slice(0, length)}…` : text
}
