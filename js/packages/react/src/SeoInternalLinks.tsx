import { useEffect, useState } from 'react'
import type { InternalLinksResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoInternalLinksProps {
  client: SeoClient
  type?: string
  className?: string
}

/**
 * Read side of `php artisan seo:internal-links` — how many internal links
 * point at each record, flagging zero incoming as an orphan.
 */
export function SeoInternalLinks({ client, type, className = '' }: SeoInternalLinksProps) {
  const [activeType, setActiveType] = useState(type)
  const [response, setResponse] = useState<InternalLinksResponse | null>(null)

  useEffect(() => {
    setActiveType(type)
  }, [type])

  useEffect(() => {
    client.internalLinks(activeType).then((data) => {
      setResponse(data)
      if (activeType === undefined && data.type) setActiveType(data.type)
    })
  }, [client, activeType])

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
              onClick={() => setActiveType(t)}
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

      {response.data.length === 0 ? (
        <div className="rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400">
          Không có bản ghi nào. Chạy <code>php artisan seo:internal-links</code> để quét link.
        </div>
      ) : (
        <div className="overflow-x-auto rounded-md border border-slate-200">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
                <th className="px-3 py-2 font-medium">Trang</th>
                <th className="px-3 py-2 font-medium">Đến</th>
                <th className="px-3 py-2 font-medium">Đi</th>
                <th className="px-3 py-2 font-medium">Trạng thái</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {response.data.map((row) => (
                <tr key={row.id}>
                  <td className="px-3 py-2 font-mono text-xs">{row.url}</td>
                  <td className="px-3 py-2">{row.incomingLinks}</td>
                  <td className="px-3 py-2">{row.outgoingLinks}</td>
                  <td className="px-3 py-2">
                    {row.isOrphan ? (
                      <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">
                        Mồ côi — không ai link tới
                      </span>
                    ) : (
                      <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">OK</span>
                    )}
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
