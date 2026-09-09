import { useEffect, useState } from 'react'
import type { BrokenLinksResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoBrokenLinksProps {
  client: SeoClient
  className?: string
}

/**
 * Currently-known-broken external URLs and which records cite them, from
 * whatever `php artisan seo:broken-links` last found.
 */
export function SeoBrokenLinks({ client, className = '' }: SeoBrokenLinksProps) {
  const [response, setResponse] = useState<BrokenLinksResponse | null>(null)

  useEffect(() => {
    client.brokenLinks().then(setResponse)
  }, [client])

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
        Không có link chết nào được ghi nhận. Chạy{' '}
        <code>php artisan seo:broken-links App\Models\Post</code> để quét.
      </div>
    )
  }

  return (
    <div className={`overflow-x-auto rounded-md border border-slate-200 text-sm ${className}`}>
      <table className="w-full text-left">
        <thead>
          <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
            <th className="px-3 py-2 font-medium">URL</th>
            <th className="px-3 py-2 font-medium">Lỗi</th>
            <th className="px-3 py-2 font-medium">Số trang trỏ tới</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {response.data.map((row) => (
            <tr key={row.url}>
              <td className="max-w-xs truncate px-3 py-2 font-mono text-xs" title={row.url}>
                {row.url}
              </td>
              <td className="px-3 py-2">
                <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700" title={row.error ?? undefined}>
                  {row.statusCode !== null ? `HTTP ${row.statusCode}` : (row.error ?? 'Lỗi')}
                </span>
              </td>
              <td className="px-3 py-2 text-slate-500" title={row.sources.map((s) => s.sourceType + ':' + s.sourceId).join(', ')}>
                {row.sources.length}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
