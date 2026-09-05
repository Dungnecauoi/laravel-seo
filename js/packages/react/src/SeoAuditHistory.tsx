import { useEffect, useState } from 'react'
import type { AuditHistoryResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoAuditHistoryProps {
  client: SeoClient
  /** Fully-qualified model class to filter by, e.g. `App\Models\Post`. */
  model?: string
  className?: string
}

/**
 * Read side of `php artisan seo:audit` — every batch it wrote, newest
 * first, so a score trend is visible without reading console output.
 */
export function SeoAuditHistory({ client, model, className = '' }: SeoAuditHistoryProps) {
  const [response, setResponse] = useState<AuditHistoryResponse | null>(null)

  useEffect(() => {
    client.auditHistory(model).then(setResponse)
  }, [client, model])

  if (!response) {
    return (
      <p className={`text-sm text-slate-500 ${className}`} role="status">
        Đang tải…
      </p>
    )
  }

  if (response.data.length === 0) {
    return (
      <div className={`rounded-md border border-dashed border-slate-300 p-6 text-center text-slate-400 ${className}`}>
        Chưa có lần audit nào. Chạy <code>php artisan seo:audit</code> để bắt đầu.
      </div>
    )
  }

  return (
    <div className={`overflow-x-auto rounded-md border border-slate-200 text-sm ${className}`}>
      <table className="w-full text-left">
        <thead>
          <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
            <th className="px-3 py-2 font-medium">Model</th>
            <th className="px-3 py-2 font-medium">Số bản ghi</th>
            <th className="px-3 py-2 font-medium">Điểm TB</th>
            <th className="px-3 py-2 font-medium">Thấp / Cao</th>
            <th className="px-3 py-2 font-medium">Chạy lúc</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {response.data.map((batch) => (
            <tr key={batch.id}>
              <td className="px-3 py-2 font-mono text-xs">{batch.model.split('\\').pop()}</td>
              <td className="px-3 py-2">{batch.totalRecords.toLocaleString('vi-VN')}</td>
              <td className="px-3 py-2">
                {batch.averageScore !== null ? (
                  <span
                    className={
                      batch.averageScore >= 80
                        ? 'rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700'
                        : batch.averageScore >= 50
                          ? 'rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700'
                          : 'rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700'
                    }
                  >
                    {batch.averageScore.toFixed(1)}
                  </span>
                ) : (
                  <span className="text-slate-400">—</span>
                )}
              </td>
              <td className="px-3 py-2 text-slate-500">
                {batch.minScore ?? '—'} / {batch.maxScore ?? '—'}
              </td>
              <td className="px-3 py-2 text-slate-500">
                {batch.startedAt ? new Date(batch.startedAt).toLocaleString('vi-VN') : '—'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
