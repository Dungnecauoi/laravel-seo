import { useEffect, useState } from 'react'
import type { UrlInspectionsResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoUrlInspectionsProps {
  client: SeoClient
  className?: string
}

/**
 * The latest URL Inspection result per URL — whether Google has it indexed,
 * and why not if it doesn't, from whichever URLs `php artisan
 * seo:search-console:inspect` was last pointed at.
 */
export function SeoUrlInspections({ client, className = '' }: SeoUrlInspectionsProps) {
  const [response, setResponse] = useState<UrlInspectionsResponse | null>(null)

  useEffect(() => {
    client.urlInspections().then(setResponse)
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
        Chưa có dữ liệu. Chạy <code>php artisan seo:search-console:inspect https://vidu.vn/trang</code> sau khi
        cấu hình Search Console.
      </div>
    )
  }

  return (
    <div className={`overflow-x-auto rounded-md border border-slate-200 text-sm ${className}`}>
      <table className="w-full text-left">
        <thead>
          <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
            <th className="px-3 py-2 font-medium">Trang</th>
            <th className="px-3 py-2 font-medium">Trạng thái</th>
            <th className="px-3 py-2 font-medium">Verdict</th>
            <th className="px-3 py-2 font-medium">Mobile</th>
            <th className="px-3 py-2 font-medium">Rich results</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {response.data.map((row) => (
            <tr key={row.url}>
              <td className="max-w-xs truncate px-3 py-2 font-mono text-xs" title={row.url}>
                {row.url}
              </td>
              <td className="px-3 py-2">{row.coverageState ?? '—'}</td>
              <td className="px-3 py-2">
                {row.verdict === 'PASS' ? (
                  <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">PASS</span>
                ) : row.verdict === 'FAIL' ? (
                  <span className="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">FAIL</span>
                ) : (
                  <span className="text-slate-400">{row.verdict ?? '—'}</span>
                )}
              </td>
              <td className="px-3 py-2 text-slate-500" title={row.mobileUsabilityIssues.join(', ')}>
                {row.mobileUsabilityVerdict ?? '—'}
                {row.mobileUsabilityIssues.length > 0 && ` (${row.mobileUsabilityIssues.length} lỗi)`}
              </td>
              <td className="px-3 py-2 text-slate-500">{row.richResultsVerdict ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
