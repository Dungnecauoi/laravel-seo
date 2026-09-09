import { useEffect, useState } from 'react'
import type { PageSpeedStatsResponse, SeoClient } from '@duxbo/seo-core'

export interface SeoPageSpeedStatsProps {
  client: SeoClient
  strategy?: 'mobile' | 'desktop'
  className?: string
}

/**
 * The latest PageSpeed Insights run per URL — performance score and Core
 * Web Vitals, from whichever URLs `php artisan seo:pagespeed` was last
 * pointed at. `fieldDataAvailable: false` renders as "not enough data" for
 * a low-traffic URL, not as an error.
 */
export function SeoPageSpeedStats({ client, strategy = 'mobile', className = '' }: SeoPageSpeedStatsProps) {
  const [response, setResponse] = useState<PageSpeedStatsResponse | null>(null)

  useEffect(() => {
    setResponse(null)
    client.pageSpeedStats(strategy).then(setResponse)
  }, [client, strategy])

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
        Chưa có dữ liệu. Chạy <code>php artisan seo:pagespeed https://vidu.vn/trang</code> sau khi cấu hình API key
        trong <code>seo.pagespeed</code>.
      </div>
    )
  }

  return (
    <div className={`overflow-x-auto rounded-md border border-slate-200 text-sm ${className}`}>
      <table className="w-full text-left">
        <thead>
          <tr className="border-b border-slate-100 text-xs uppercase text-slate-400">
            <th className="px-3 py-2 font-medium">Trang</th>
            <th className="px-3 py-2 font-medium">Điểm</th>
            <th className="px-3 py-2 font-medium">LCP</th>
            <th className="px-3 py-2 font-medium">CLS</th>
            <th className="px-3 py-2 font-medium">CWV (thực tế)</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-slate-100">
          {response.data.map((row) => (
            <tr key={row.url}>
              <td className="max-w-xs truncate px-3 py-2 font-mono text-xs" title={row.url}>
                {row.url}
              </td>
              <td className="px-3 py-2">
                {row.performanceScore === null ? (
                  '—'
                ) : (
                  <span
                    className={`rounded-full px-2 py-0.5 text-xs ${
                      row.performanceScore >= 90
                        ? 'bg-emerald-50 text-emerald-700'
                        : row.performanceScore >= 50
                          ? 'bg-amber-50 text-amber-700'
                          : 'bg-red-50 text-red-700'
                    }`}
                  >
                    {`${row.performanceScore}/100`}
                  </span>
                )}
              </td>
              <td className="px-3 py-2">{row.lcpMs === null ? '—' : `${(row.lcpMs / 1000).toFixed(1)}s`}</td>
              <td className="px-3 py-2">{row.clsScore === null ? '—' : row.clsScore.toFixed(3)}</td>
              <td className="px-3 py-2 text-slate-500">
                {row.fieldDataAvailable ? row.cwvCategory : 'Chưa đủ dữ liệu'}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
